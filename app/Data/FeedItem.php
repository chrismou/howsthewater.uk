<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * One entry in a spot or river RSS feed. The guid never changes for the
 * same event, so feed readers only notify once.
 */
final readonly class FeedItem
{
    public function __construct(
        public string $guid,
        public string $title,
        public string $description,
        public string $link,
        public Carbon $publishedAt,
    ) {
    }
}
