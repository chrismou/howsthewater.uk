<?php

namespace Database\Factories;

use App\Models\ShareImage;
use App\Models\Spot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShareImage>
 */
class ShareImageFactory extends Factory
{
    public function definition(): array
    {
        $fingerprint = fake()->md5();

        return [
            'shareable_type' => Spot::class,
            'shareable_id' => Spot::factory(),
            'path' => 'share/spots/1-'.$fingerprint.'.png',
            'fingerprint' => $fingerprint,
            'alt' => 'Ilkley on the River Wharfe, Yorkshire, marked on a map of the river',
        ];
    }
}
