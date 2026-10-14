<?php

namespace App\Models;

use App\Enums\BathingWaterClassification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One year's classification for a bathing water.
 *
 * @property int $id
 * @property int $bathing_water_id
 * @property int $year
 * @property BathingWaterClassification $classification
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BathingWaterClassificationRecord extends Model
{
    protected $table = 'bathing_water_classifications';

    protected $fillable = [
        'bathing_water_id',
        'year',
        'classification',
    ];

    protected function casts(): array
    {
        return [
            'classification' => BathingWaterClassification::class,
        ];
    }

    /** @return BelongsTo<BathingWater, $this> */
    public function bathingWater(): BelongsTo
    {
        return $this->belongsTo(BathingWater::class);
    }
}
