<?php

namespace App\Models;

use App\Data\GaugeStationData;
use App\Enums\Agency;
use Database\Factories\GaugeStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $external_id
 * @property string|null $rloi_id The Environment Agency's River Levels on the Internet id, for its own gauge page
 * @property Agency $network
 * @property int|null $river_id
 * @property string $label
 * @property string|null $river_name
 * @property string|null $town
 * @property float $latitude
 * @property float $longitude
 * @property int|null $river_metres
 * @property string|null $measure_id
 * @property string|null $unit
 * @property float|null $typical_range_low
 * @property float|null $typical_range_high
 * @property float|null $record_level The highest level the agency holds for the gauge
 * @property Carbon|null $record_level_at
 * @property bool $record_level_known_to_month SEPA publishes monthly maxima, so only the month of its record is known
 * @property Carbon|null $records_from When the agency's records for the gauge begin, where it says
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read River|null $river
 */
class GaugeStation extends Model
{
    /** @use HasFactory<GaugeStationFactory> */
    use HasFactory;

    protected $fillable = [
        'external_id',
        'rloi_id',
        'network',
        'river_id',
        'label',
        'river_name',
        'town',
        'latitude',
        'longitude',
        'river_metres',
        'measure_id',
        'unit',
        'typical_range_low',
        'typical_range_high',
        'record_level',
        'record_level_at',
        'record_level_known_to_month',
        'records_from',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'network' => Agency::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'typical_range_low' => 'float',
            'typical_range_high' => 'float',
            'record_level' => 'float',
            'record_level_at' => 'datetime',
            'record_level_known_to_month' => 'boolean',
            'records_from' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The highest level on record in words, for pages: "3.62 m in December 2015" (SEPA, known to the month) or
     * "7.54 m on 7 January 2024" (the Environment Agency), or null where the agency publishes none.
     */
    public function recordLevelPhrase(): ?string
    {
        if ($this->record_level === null || $this->record_level_at === null) {
            return null;
        }

        $at = $this->record_level_at->copy()->timezone(config('app.display_timezone', 'Europe/London'));

        return number_format($this->record_level, 2).' '.$this->unitLabel().' '
            .($this->record_level_known_to_month ? 'in '.$at->format('F Y') : 'on '.$at->format('j F Y'));
    }

    /**
     * Readings in mAOD are heights above sea level rather than above the
     * gauge datum, so the number means little without that context.
     */
    public function isAboveOrdnanceDatum(): bool
    {
        return strcasecmp((string) $this->unit, 'mAOD') === 0;
    }

    /**
     * The agency's own public page for this gauge, with its history, or null where we do not know its address yet
     * (an Environment Agency gauge before station discovery has read its RLOI id).
     */
    public function agencyPageUrl(): ?string
    {
        return match ($this->network) {
            Agency::EnvironmentAgency => $this->rloi_id === null ? null : 'https://check-for-flooding.service.gov.uk/station/'.rawurlencode($this->rloi_id),
            Agency::Sepa => 'https://waterlevels.sepa.org.uk/Station/'.rawurlencode(Str::after($this->external_id, 'SEPA')),
            Agency::NaturalResourcesWales => 'https://rivers-and-seas.naturalresources.wales/Station/'.rawurlencode(Str::after($this->external_id, GaugeStationData::NRW_PREFIX)),
        };
    }

    /**
     * Who runs the gauge's own page, as a link reads ("on GOV.UK").
     */
    public function agencyPageName(): string
    {
        return match ($this->network) {
            Agency::EnvironmentAgency => 'GOV.UK',
            Agency::Sepa => "SEPA's site",
            Agency::NaturalResourcesWales => "Natural Resources Wales's site",
        };
    }

    /**
     * Stage readings are shown as plain metres. Some gauges (the Thames
     * locks, for one) label the same measure mASD, metres above stage datum,
     * which means nothing to most readers. mAOD stays as it is, since that
     * really is a different measure.
     */
    public function unitLabel(): string
    {
        if ($this->unit === null || strcasecmp($this->unit, 'mASD') === 0) {
            return 'm';
        }

        return $this->unit;
    }

    /** @return BelongsTo<River, $this> */
    public function river(): BelongsTo
    {
        return $this->belongsTo(River::class);
    }

    /** @return HasMany<GaugeReading, $this> */
    public function readings(): HasMany
    {
        return $this->hasMany(GaugeReading::class)->orderByDesc('measured_at');
    }

    /** @return HasMany<Spot, $this> */
    public function spots(): HasMany
    {
        return $this->hasMany(Spot::class);
    }
}
