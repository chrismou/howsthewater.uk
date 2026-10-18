<?php

namespace App\Services\EnvironmentAgency;

use App\Data\SpillLogRowData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\LazyCollection;
use RuntimeException;
use Spatie\SimpleExcel\SimpleExcelReader;

/**
 * Reads the Environment Agency's "Event Duration Monitoring - Storm Overflow - Start/Stop Detailed Data" (OGL): one
 * file per water company per year listing every spill's start and stop, as the companies submit them. The dataset
 * page lists its files only in the data embedded for its own scripts, so that is where the list is read from.
 */
class SpillLogFilesClient
{
    /**
     * Every file in the dataset.
     *
     * @return Collection<int, array{name: string, size: int, modified: string}>
     */
    public function files(): Collection
    {
        $html = $this->request()->get((string) config('services.overflow_spill_logs.environment_agency.dataset_url'))->throw()->body();

        if (preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $match) !== 1) {
            throw new RuntimeException('The start/stop dataset page no longer embeds its file list.');
        }

        $files = data_get(json_decode($match[1], true), 'props.pageProps.files');

        if (! is_array($files)) {
            throw new RuntimeException('The start/stop dataset page has no file list.');
        }

        return collect($files)
            ->filter(fn (mixed $file) => is_array($file) && is_string($file['name'] ?? null))
            ->map(fn (array $file) => [
                'name' => $file['name'],
                'size' => (int) ($file['size'] ?? 0),
                'modified' => (string) ($file['lastModified'] ?? ''),
            ])
            ->values();
    }

    /**
     * Saves one file to $path.
     */
    public function download(string $name, string $path): void
    {
        $this->request()
            ->timeout(600)
            ->sink($path)
            // The download URL already carries the dataset in its query string, which a query array would replace.
            ->get(config('services.overflow_spill_logs.environment_agency.download_url').'&fileName='.rawurlencode($name))
            ->throw();
    }

    /**
     * Every spill in a downloaded file, from every sheet. Columns are read by position (id, site name, start, stop),
     * as each company names them differently, and the type is read from the file itself, since some ".csv" files
     * are spreadsheets.
     *
     * @return LazyCollection<int, SpillLogRowData>
     */
    public function rows(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $handle = fopen($path, 'rb');
            $isSpreadsheet = $handle !== false && fread($handle, 2) === 'PK';

            if ($handle !== false) {
                fclose($handle);
            }

            $type = $isSpreadsheet ? 'xlsx' : 'csv';
            $sheets = $isSpreadsheet ? count(SimpleExcelReader::create($path, $type)->getSheetNames()) : 1;

            for ($sheet = 1; $sheet <= $sheets; $sheet++) {
                $reader = SimpleExcelReader::create($path, $type)->noHeaderRow();

                foreach (($isSpreadsheet ? $reader->fromSheet($sheet) : $reader)->getRows() as $cells) {
                    $row = SpillLogRowData::fromCells($cells[0] ?? null, $cells[2] ?? null, $cells[3] ?? null);

                    if ($row !== null) {
                        yield $row;
                    }
                }
            }
        });
    }

    private function request(): PendingRequest
    {
        return Http::timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
