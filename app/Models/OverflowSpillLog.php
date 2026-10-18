<?php

namespace App\Models;

use App\Enums\SpillLogSource;
use Database\Factories\OverflowSpillLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One published spill log as last read: an Environment Agency start/stop file (one company's year) or a Stream log
 * service. Its spills are the spill_events that point at it, and its covered period tells pages which months the
 * company has published.
 *
 * @property int $id
 * @property SpillLogSource $source
 * @property string $key
 * @property string $name
 * @property string|null $company
 * @property int|null $year
 * @property string|null $fingerprint
 * @property Carbon|null $covers_from
 * @property Carbon|null $covers_until
 * @property int $spills
 * @property int $unmatched
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class OverflowSpillLog extends Model
{
    /** @use HasFactory<OverflowSpillLogFactory> */
    use HasFactory;

    protected $fillable = [
        'source',
        'key',
        'name',
        'company',
        'year',
        'fingerprint',
        'covers_from',
        'covers_until',
        'spills',
        'unmatched',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => SpillLogSource::class,
            'year' => 'integer',
            'covers_from' => 'datetime',
            'covers_until' => 'datetime',
            'spills' => 'integer',
            'unmatched' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /** @return HasMany<SpillEvent, $this> */
    public function spillEvents(): HasMany
    {
        return $this->hasMany(SpillEvent::class);
    }
}
