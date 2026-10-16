<?php

namespace App\Services\Sepa;

use App\Data\BathingWaterSampleData;
use App\Enums\BathingWaterClassification;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads one Scottish bathing water's results page: this season's samples
 * and its past classifications. SEPA publishes these nowhere else, so the
 * page is read as HTML, one site per request.
 */
class BathingWaterResultsClient
{
    /**
     * @return array{samples: Collection<int, BathingWaterSampleData>, classifications: array<int, BathingWaterClassification>}
     */
    public function fetch(string $url): array
    {
        $html = Http::timeout(30)
            ->withUserAgent(config('services.user_agent'))
            ->get($url)
            ->throw()
            ->body();

        $page = new DOMDocument();

        if ($html === '' || ! @$page->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            throw new RuntimeException("SEPA results page {$url} could not be read");
        }

        $xpath = new DOMXPath($page);

        // A page without the samples table has changed shape, which should be seen rather than read as "no samples".
        $sampleTables = $xpath->query('//table[@id="tblSampleResults"]');

        if ($sampleTables === false || $sampleTables->length !== 1) {
            throw new RuntimeException("SEPA results page {$url} has no sample table");
        }

        $samples = collect($this->rows($xpath, 'tblSampleResults'))
            ->filter(fn (array $cells) => count($cells) >= 3)
            ->map(fn (array $cells) => BathingWaterSampleData::fromSepa($cells[0], $cells[1], $cells[2]))
            ->filter()
            ->values();

        $classifications = [];

        foreach ($this->rows($xpath, 'tblHistoricClassifications') as $cells) {
            $classification = BathingWaterClassification::tryFrom($cells[1] ?? '');

            if (preg_match('/^\d{4}$/', $cells[0]) === 1 && $classification !== null) {
                $classifications[(int) $cells[0]] = $classification;
            }
        }

        return ['samples' => $samples, 'classifications' => $classifications];
    }

    /**
     * The trimmed cell text of each body row of a table.
     *
     * @return list<list<string>>
     */
    private function rows(DOMXPath $xpath, string $tableId): array
    {
        $rows = [];

        foreach ($xpath->query("//table[@id=\"{$tableId}\"]/tbody/tr") ?: [] as $row) {
            if (! $row instanceof DOMElement) {
                continue;
            }

            $cells = [];

            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? '');
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
