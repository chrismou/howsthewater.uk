<?php

namespace App\Actions\Ingest;

use App\Enums\Agency;
use App\Models\BathingWater;
use App\Services\Sepa\BathingWaterResultsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Reads Scottish samples from SEPA's results pages, one site at a time at
 * an even pace: every site once per interval_days, spread evenly over that
 * time (with 90 sites and 7 days, one request every 112 minutes). So SEPA
 * sees a steady trickle, and no result waits longer than the interval to
 * be picked up. Timing reads to each site's expected next result was
 * tried and did no better, since sampling dates are too irregular.
 */
class SyncSepaBathingWaterSamples
{
    /**
     * Pre-season samples start in mid May and the last results are out by
     * the end of September, as UK month and day.
     */
    private const SEASON_STARTS = '05-15';

    private const SEASON_ENDS = '09-30';

    private const PAUSE_KEY = 'sepa:bathing-samples:paused';

    public function __construct(private BathingWaterResultsClient $client, private SyncBathingWaters $sync)
    {
    }

    /**
     * @return array{checked: int, samples: int, regions: list<int>, skipped: string|null, failed: string|null}
     */
    public function handle(): array
    {
        $result = ['checked' => 0, 'samples' => 0, 'regions' => [], 'skipped' => null, 'failed' => null];

        $skipped = match (true) {
            ! config('services.sepa.bathing_samples.enabled') => 'switched off',
            ! $this->isInSeason(now()) => 'out of season',
            Cache::has(self::PAUSE_KEY) => 'paused after a failed request',
            ! $this->isTurnDue() => 'not due yet',
            default => null,
        };

        if ($skipped !== null) {
            return [...$result, 'skipped' => $skipped];
        }

        // The site read longest ago, skipping any whose last read failed until it may be tried again.
        $site = $this->sites()
            ->where(fn ($query) => $query->whereNull('samples_retry_at')->orWhere('samples_retry_at', '<=', now()))
            ->orderBy('samples_checked_at')
            ->orderBy('id')
            ->first();

        if ($site === null) {
            return [...$result, 'skipped' => 'no site to read'];
        }

        try {
            $page = $this->client->fetch((string) $site->profile_url);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            // A refusal means back right off; anything else may be one broken page, so only that site waits a day.
            $refused = $exception instanceof RequestException && in_array($exception->response->status(), [403, 429], true);
            Cache::put(self::PAUSE_KEY, true, $refused ? now()->addDay() : now()->addHour());
            $site->update(['samples_retry_at' => now()->addDay()]);

            $reason = $exception instanceof RequestException ? "HTTP {$exception->response->status()}" : $exception->getMessage();

            return [...$result, 'failed' => "{$site->name}: {$reason}"];
        }

        $new = $this->sync->storeSamples($site, $page['samples']);
        $changed = $new > 0 || $site->samples_checked_at === null;

        foreach ($page['classifications'] as $year => $classification) {
            $record = $site->classifications()->updateOrCreate(['year' => $year], ['classification' => $classification]);
            $changed = $changed || $record->wasRecentlyCreated || $record->wasChanged();
        }

        $site->update(['samples_checked_at' => now(), 'samples_retry_at' => null]);

        // Pages only need rebuilding when something on them changed.
        $regions = $changed && $site->region_id !== null ? [$site->region_id] : [];

        return [...$result, 'checked' => 1, 'samples' => $new, 'regions' => $regions];
    }

    /**
     * Days for one pass over every site, never under a day.
     */
    public function intervalDays(): float
    {
        return max(1.0, (float) config('services.sepa.bathing_samples.interval_days', 7));
    }

    /**
     * Minutes between any two reads: one pass over every site per interval.
     */
    public function minutesBetweenReads(): float
    {
        return $this->intervalDays() * 24 * 60 / max(1, $this->sites()->count());
    }

    /**
     * Whether the last read, of any site, was long enough ago for the next.
     */
    private function isTurnDue(): bool
    {
        $lastRead = $this->sites()->max('samples_checked_at');

        return $lastRead === null || Carbon::parse($lastRead)->addSeconds((int) round($this->minutesBetweenReads() * 60))->lessThanOrEqualTo(now());
    }

    /**
     * @return Builder<BathingWater>
     */
    private function sites(): Builder
    {
        return BathingWater::query()->where('agency', Agency::Sepa)->whereNotNull('profile_url');
    }

    private function isInSeason(Carbon $now): bool
    {
        $today = $now->copy()->timezone('Europe/London')->format('m-d');

        return $today >= self::SEASON_STARTS && $today <= self::SEASON_ENDS;
    }
}
