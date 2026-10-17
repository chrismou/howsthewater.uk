<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * The few sentences under a place's heading that say what people look for at a glance, then notes on the weather
 * when it crosses a threshold, then where the figures come from. Each sentence is a list of runs of text, bold where
 * a run is the headline fact.
 */
final readonly class Headline
{
    /**
     * @param  list<list<array{0: string, 1: bool}>>  $sentences
     * @param  list<list<array{0: string, 1: bool}>>  $notes
     * @param  string|null  $overflowSource  Such as "9 Yorkshire Water monitors upstream"
     * @param  string|null  $levelSource  Such as "the Ilkley gauge"
     */
    public function __construct(
        public array $sentences,
        public array $notes,
        public ?string $overflowSource,
        public ?Carbon $overflowAt,
        public ?string $levelSource,
        public ?Carbon $levelAt,
    ) {
    }

    /**
     * The sentences and notes as plain text, for tests and anywhere markup is not wanted.
     */
    public function plainText(): string
    {
        return collect([...$this->sentences, ...$this->notes])
            ->map(fn (array $runs) => collect($runs)->map(fn (array $run) => $run[0])->join(''))
            ->join(' ');
    }
}
