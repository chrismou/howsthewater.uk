<?php

namespace App\Models;

use App\Data\SpotConditions;
use App\Enums\OverflowStatus;
use App\Services\Geo\OverflowSiteName;
use Database\Factories\OverflowMonitorFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $external_id
 * @property string|null $name The feed's own name, where it gives one
 * @property string|null $return_name The site name from its latest annual return
 * @property int|null $river_id
 * @property string $company
 * @property string|null $receiving_watercourse
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property OverflowStatus $status
 * @property Carbon|null $status_started_at
 * @property Carbon|null $latest_event_started_at
 * @property Carbon|null $latest_event_ended_at
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River|null $river
 * @property-read Collection<int, SpillEvent> $openSpillEvents
 * @property-read Collection<int, OverflowAnnualReturn> $annualReturns
 * @property-read Collection<int, OverflowMonitorPlace> $places
 * @property-read Collection<int, OverflowOfflinePeriod> $offlinePeriods
 * @property-read ShareImage|null $shareImage
 */
class OverflowMonitor extends Model
{
    /** @use HasFactory<OverflowMonitorFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'name',
        'return_name',
        'river_id',
        'company',
        'receiving_watercourse',
        'latitude',
        'longitude',
        'river_metres',
        'status',
        'status_started_at',
        'latest_event_started_at',
        'latest_event_ended_at',
        'source_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'status' => OverflowStatus::class,
            'status_started_at' => 'datetime',
            'latest_event_started_at' => 'datetime',
            'latest_event_ended_at' => 'datetime',
            'source_updated_at' => 'datetime',
        ];
    }

    /**
     * Overflow pages are addressed by the feed's id, lower case (/overflows/yws02215). Ids are unique across feeds.
     */
    public function getRouteKeyName(): string
    {
        return 'external_id';
    }

    public function getRouteKey(): string
    {
        return strtolower($this->external_id);
    }

    /**
     * Every feed's ids are upper case, so a lower case address finds them on any database collation.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return $this->newQuery()->where($field ?? $this->getRouteKeyName(), strtoupper((string) $value))->first();
    }

    /** @return BelongsTo<River, $this> */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    /** @return HasMany<SpillEvent, $this> */
    public function spillEvents(): HasMany
    {
        return $this->hasMany(SpillEvent::class)->orderByDesc('started_at');
    }

    /**
     * Events the feed has not reported an end for. Normally at most one.
     *
     * @return HasMany<SpillEvent, $this>
     */
    public function openSpillEvents(): HasMany
    {
        return $this->hasMany(SpillEvent::class)->whereNull('ended_at');
    }

    /**
     * Each year's official return for this overflow, newest first.
     *
     * @return HasMany<OverflowAnnualReturn, $this>
     */
    public function annualReturns(): HasMany
    {
        return $this->hasMany(OverflowAnnualReturn::class)->orderByDesc('year');
    }

    /**
     * The spots it is upstream of and the beaches and lakes it is near, as rebuilt by regions:refresh.
     *
     * @return HasMany<OverflowMonitorPlace, $this>
     */
    public function places(): HasMany
    {
        return $this->hasMany(OverflowMonitorPlace::class);
    }

    /** @return MorphOne<ShareImage, $this> */
    public function shareImage(): MorphOne
    {
        return $this->morphOne(ShareImage::class, 'shareable');
    }

    /** @return HasMany<OverflowOfflinePeriod, $this> */
    public function offlinePeriods(): HasMany
    {
        return $this->hasMany(OverflowOfflinePeriod::class)->orderByDesc('started_at');
    }

    /**
     * The overflow's name: the feed's own, else its annual return's. Null when neither has one (a Stream overflow
     * with no matching return).
     */
    public function displayName(): ?string
    {
        return $this->name ?? $this->return_name;
    }

    /**
     * The name to show it by: its own, else "Storm overflow YWS02215".
     */
    public function titleName(): string
    {
        return $this->displayName() ?? "Storm overflow {$this->external_id}";
    }

    /**
     * The company without "Ltd", "plc" or "Services" ("Anglian Water Services" reads as Anglian Water).
     */
    public function companyName(): string
    {
        return (string) preg_replace('/\s+(Ltd|Limited|plc|Services)\.?$/i', '', $this->company);
    }

    /**
     * "a Yorkshire Water storm overflow", "an Anglian Water storm overflow".
     */
    public function companyPhrase(): string
    {
        $company = $this->companyName();

        return (preg_match('/^[aeiou]/i', $company) === 1 ? 'an ' : 'a ')."{$company} storm overflow";
    }

    /**
     * "into the River Wharfe", "into Thornton Beck", "into Loch Gairloch": "the" only before a river. Null when it is
     * not known. Load the river first.
     */
    public function intoPhrase(): ?string
    {
        $into = $this->intoName();

        return $into === null ? null : 'into '.(preg_match('/^(River|Afon)\s/i', $into) === 1 ? 'the ' : '').$into;
    }

    /**
     * What the overflow discharges into, for lists: its river's name where it is on one, else the feed's receiving
     * watercourse, tidied. Load the river first.
     */
    public function intoName(): ?string
    {
        return $this->river->name ?? OverflowSiteName::tidy($this->receiving_watercourse);
    }

    public function isDischarging(): bool
    {
        return $this->status === OverflowStatus::Discharging;
    }

    /**
     * Whether the feed has not confirmed this monitor's status within SpotConditions::STALE_AFTER_HOURS, so its
     * status is only what it was at the last report.
     */
    public function isStale(): bool
    {
        return $this->source_updated_at === null
            || $this->source_updated_at->lessThan(now()->subHours(SpotConditions::STALE_AFTER_HOURS));
    }
}
