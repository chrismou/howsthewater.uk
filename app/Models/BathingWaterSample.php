<?php

namespace App\Models;

use Database\Factories\BathingWaterSampleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One in-season sample: bacteria counts per 100 ml. A qualifier of "<" or
 * ">" means the true count is below or above the figure reported.
 *
 * @property int $id
 * @property int $bathing_water_id
 * @property Carbon $sampled_at
 * @property int|null $e_coli
 * @property string|null $e_coli_qualifier
 * @property int|null $enterococci
 * @property string|null $enterococci_qualifier
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BathingWaterSample extends Model
{
    /** @use HasFactory<BathingWaterSampleFactory> */
    use HasFactory;

    protected $fillable = [
        'bathing_water_id',
        'sampled_at',
        'e_coli',
        'e_coli_qualifier',
        'enterococci',
        'enterococci_qualifier',
    ];

    protected function casts(): array
    {
        return [
            'sampled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<BathingWater, $this> */
    public function bathingWater(): BelongsTo
    {
        return $this->belongsTo(BathingWater::class);
    }

    /**
     * A count as published, e.g. "<10" or "27".
     */
    public static function formatCount(?int $count, ?string $qualifier): ?string
    {
        if ($count === null) {
            return null;
        }

        return ($qualifier === '<' || $qualifier === '>' ? $qualifier : '').number_format($count);
    }
}
