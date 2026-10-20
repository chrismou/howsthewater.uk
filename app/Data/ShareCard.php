<?php

namespace App\Data;

/**
 * Everything drawn on a page's share card (ShareCardRenderer): the words on the left, with what the page covers as
 * chips, and on the right a map card with either the river's line and its spots, or Britain, and this card's place
 * pinned. It never carries live status: social sites keep a card for days, long after a spill has started or stopped.
 */
final readonly class ShareCard
{
    /**
     * @param  list<string>  $topics  What the page covers ("Overflow spills", "River level"), drawn as chips
     * @param  string  $alt  Describes the card for screen readers, as og:image:alt
     * @param  list<array{0: float, 1: float}>|null  $riverLine  The river's centreline, source first, as [lat, lng]
     * @param  list<array{0: float, 1: float}>  $riverSpots  Spots on the river, drawn as rings
     * @param  array{0: float, 1: float}|null  $pin  The place this card is for, as [lat, lng]
     * @param  bool  $pinIsOverflow  Whether the pin is an overflow, drawn square as the site's maps draw overflows
     */
    public function __construct(
        public string $eyebrow,
        public string $title,
        public string $subtitle,
        public array $topics,
        public string $alt,
        public ?array $riverLine = null,
        public array $riverSpots = [],
        public ?array $pin = null,
        public bool $pinIsOverflow = false,
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
