<?php

namespace App\Data;

/**
 * Everything drawn on a page's share card (ShareCardRenderer): the words on the left, and on the right either the
 * river's line with its spots, or Britain with a pin. It never carries live status: social sites keep a card for days,
 * long after a spill has started or stopped.
 */
final readonly class ShareCard
{
    /**
     * @param  string  $alt  Describes the card for screen readers, as og:image:alt
     * @param  list<array{0: float, 1: float}>|null  $riverLine  The river's centreline, source first, as [lat, lng]
     * @param  list<array{0: float, 1: float}>  $riverSpots  Spots on the river, drawn as rings
     * @param  array{0: float, 1: float}|null  $pin  The place this card is for, as [lat, lng]
     */
    public function __construct(
        public string $eyebrow,
        public string $title,
        public string $subtitle,
        public string $tagline,
        public string $alt,
        public ?array $riverLine = null,
        public array $riverSpots = [],
        public ?array $pin = null,
    ) {
    }

    /**
     * Changes whenever anything drawn changes, including how cards are drawn ($version).
     */
    public function fingerprint(string $version): string
    {
        return hash('xxh128', $version.serialize($this));
    }
}
