<?php

namespace App\Models;

use App\Enums\Agency;
use Database\Factories\FloodAreaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * An Environment Agency or Natural Resources Wales flood alert or flood warning area. Spots link to the
 * areas that cover them and name their river, so a warning for a beck that
 * happens to pass nearby is not shown.
 *
 * @property int $id
 * @property string $code
 * @property Agency $agency
 * @property string $label
 * @property string|null $description
 * @property string|null $river_or_sea
 * @property string|null $county
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read FloodWarning|null $warning
 */
class FloodArea extends Model
{
    /** @use HasFactory<FloodAreaFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'agency',
        'label',
        'description',
        'river_or_sea',
        'county',
    ];

    protected function casts(): array
    {
        return [
            'agency' => Agency::class,
        ];
    }

    /** @return BelongsToMany<Spot, $this> */
    public function spots(): BelongsToMany
    {
        return $this->belongsToMany(Spot::class);
    }

    /** @return HasMany<FloodWarningEvent, $this> */
    public function warningEvents(): HasMany
    {
        return $this->hasMany(FloodWarningEvent::class);
    }

    /** @return HasOne<FloodWarning, $this> */
    public function warning(): HasOne
    {
        return $this->hasOne(FloodWarning::class);
    }
}
