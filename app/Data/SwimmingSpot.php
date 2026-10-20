<?php

namespace App\Data;

use App\Enums\BathingWaterClassification;
use App\Enums\SpillRecency;

/**
 * A place to swim as the home page's "Right now" list and the swimming page show it: a river spot, beach or lake,
 * with its live status and what else is happening there, if anything.
 */
final readonly class SwimmingSpot
{
    /**
     * @param  string  $detail  What and where it is, short of the region ("River Wharfe", "Beach", "Lake")
     * @param  string  $kind  river, beach or lake
     * @param  bool  $designated  Whether it is (or has) a designated bathing water
     * @param  bool  $nearbyWording  Whether the spill status is for overflows nearby (beaches, lakes) rather than upstream
     * @param  string|null  $note  What else is in force there (a pollution risk forecast, a flood warning)
     * @param  int|null  $rank  How pressing it is (SwimmingSpots::RANK_*), or null when nothing is happening
     */
    public function __construct(
        public string $name,
        public string $detail,
        public string $url,
        public float $latitude,
        public float $longitude,
        public string $regionSlug,
        public string $regionName,
        public string $kind,
        public bool $designated,
        public ?BathingWaterClassification $classification,
        public SpillRecency $spillRecency,
        public bool $stale,
        public bool $nearbyWording,
        public ?string $note,
        public ?int $rank,
    ) {
    }

    public function isHappening(): bool
    {
        return $this->rank !== null;
    }

    /**
     * The status pill's words, as components/spill-badge words them.
     */
    public function statusLabel(): string
    {
        if ($this->stale) {
            return 'Overflow data out of date';
        }

        return $this->nearbyWording ? $this->spillRecency->nearbyLabel() : $this->spillRecency->label();
    }

    /**
     * What and where it is in a line: its river or kind, its classification (or that a river spot is a designated
     * bathing water) and anything else in force there.
     */
    public function description(): string
    {
        $description = $this->detail.match (true) {
            $this->classification !== null => ', classified '.$this->classification->value,
            $this->kind === 'river' && $this->designated => ', designated bathing water',
            default => '',
        };

        return $this->note === null ? $description : $description.' · '.$this->note;
    }

    /**
     * The place as the swimming page's map and "near me" list read it from /swimming/places.json: the map's marker
     * fields (lat, lng, label, url, kind, recency, and the popup's note and link text) plus the row's words and pill colour. Links are site-relative.
     *
     * @return array{lat: float, lng: float, label: string, url: string, kind: string, recency: string, note: string|null, link: string, type: string, region: string, detail: string, pill: string, tone: string}
     */
    public function toMapPlace(): array
    {
        return [
            'lat' => round($this->latitude, 5),
            'lng' => round($this->longitude, 5),
            'label' => $this->name,
            'url' => (string) parse_url($this->url, PHP_URL_PATH),
            'kind' => 'place',
            'recency' => $this->stale ? 'no_data' : $this->spillRecency->value,
            'note' => $this->spillRecency->mapNote($this->stale, $this->nearbyWording),
            'link' => 'View '.($this->kind === 'river' ? 'spot' : $this->kind),
            'type' => $this->kind,
            'region' => $this->regionName,
            'detail' => $this->description(),
            'pill' => $this->statusLabel(),
            'tone' => $this->stale ? 'grey' : $this->spillRecency->tone(),
        ];
    }
}
