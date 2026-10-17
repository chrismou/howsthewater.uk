<?php

namespace App\Models;

use Database\Factories\OverflowAnnualReturnFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One overflow's official event duration monitoring return for a year, as the water company reported it to the
 * Environment Agency or Natural Resources Wales: spills counted by the 12/24 hour method, total hours and how much of
 * the year the monitor worked. Imported by ingest:overflow-annual-returns and matched to a monitor where possible.
 *
 * @property int $id
 * @property int $year
 * @property int|null $overflow_monitor_id
 * @property string|null $uid
 * @property string $company
 * @property string|null $site_name
 * @property string|null $permit_reference
 * @property int|null $counted_spills
 * @property string|null $total_hours
 * @property string|null $monitor_uptime_percent
 * @property string|null $receiving_water
 * @property float|null $latitude
 * @property float|null $longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read OverflowMonitor|null $overflowMonitor
 */
class OverflowAnnualReturn extends Model
{
    /** @use HasFactory<OverflowAnnualReturnFactory> */
    use HasFactory;

    protected $fillable = [
        'year',
        'overflow_monitor_id',
        'uid',
        'company',
        'site_name',
        'permit_reference',
        'counted_spills',
        'total_hours',
        'monitor_uptime_percent',
        'receiving_water',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'counted_spills' => 'integer',
            'total_hours' => 'decimal:2',
            'monitor_uptime_percent' => 'decimal:2',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /** @return BelongsTo<OverflowMonitor, $this> */
    public function overflowMonitor(): BelongsTo
    {
        return $this->belongsTo(OverflowMonitor::class);
    }
}
