<?php

namespace App\Models;

use Database\Factories\ShareImageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * The share card drawn for a spot, river, beach or lake page (see GenerateShareImages), stored on the public disk.
 * The fingerprint covers everything drawn on it, and the file name carries it, so a changed card gets a new address
 * that social sites fetch afresh.
 *
 * @property int $id
 * @property string $shareable_type
 * @property int $shareable_id
 * @property string $path
 * @property string $fingerprint
 * @property string $alt
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Spot|River|BathingWater|Lake $shareable
 */
class ShareImage extends Model
{
    /** @use HasFactory<ShareImageFactory> */
    use HasFactory;

    public const DISK = 'public';

    protected $fillable = [
        'path',
        'fingerprint',
        'alt',
    ];

    /** @return MorphTo<Model, $this> */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->path);
    }
}
