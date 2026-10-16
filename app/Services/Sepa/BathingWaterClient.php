<?php

namespace App\Services\Sepa;

use App\Data\BathingWaterData;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads Scotland's designated bathing waters from SEPA's open map service
 * (OGL): each site's area, latest classification and results page from the
 * polygons layer, and its location and classification year from the points
 * layer. Sample results and daily predictions are not in the service.
 */
class BathingWaterClient
{
    private const POINTS_LAYER = 1;

    private const POLYGONS_LAYER = 2;

    /**
     * SEPA gives no council area, which the beaches menu groups by, so each
     * site's is listed here. A new site falls back to "Scotland" until added.
     *
     * @var array<string, string>
     */
    private const COUNCIL_AREAS = [
        'UKS761601' => 'Aberdeen City',
        'UKS761602' => 'Fife',
        'UKS7616086' => 'Fife',
        'UKS7616066' => 'Highland',
        'UKS7616087' => 'Fife',
        'UKS761603' => 'Angus',
        'UKS761604' => 'South Ayrshire',
        'UKS7616097' => 'Highland',
        'UKS761605' => 'Aberdeenshire',
        'UKS7616094' => 'South Ayrshire',
        'UKS761606' => 'Dumfries and Galloway',
        'UKS7616067' => 'East Lothian',
        'UKS761607' => 'Dundee City',
        'UKS761608' => 'Fife',
        'UKS761609' => 'Angus',
        'UKS7616010' => 'Dumfries and Galloway',
        'UKS7616055' => 'Scottish Borders',
        'UKS7616090' => 'Aberdeenshire',
        'UKS7616011' => 'Fife',
        'UKS7616093' => 'Aberdeenshire',
        'UKS7616013' => 'Moray',
        'UKS7616068' => 'South Ayrshire',
        'UKS7616069' => 'Dumfries and Galloway',
        'UKS7616015' => 'Highland',
        'UKS7616016' => 'Highland',
        'UKS7616017' => 'East Lothian',
        'UKS7616018' => 'East Lothian',
        'UKS7616019' => 'Highland',
        'UKS7616064' => 'Fife',
        'UKS7616065' => 'Fife',
        'UKS7616021' => 'Argyll and Bute',
        'UKS7616022' => 'Scottish Borders',
        'UKS7616070' => 'Moray',
        'UKS7616089' => 'East Lothian',
        'UKS7616043' => 'Aberdeenshire',
        'UKS7616023' => 'Aberdeenshire',
        'UKS7616092' => 'Highland',
        'UKS7616024' => 'Argyll and Bute',
        'UKS7616025' => 'South Ayrshire',
        'UKS7616026' => 'East Lothian',
        'UKS7616071' => 'South Ayrshire',
        'UKS7616027' => 'Aberdeenshire',
        'UKS7616028' => 'North Ayrshire',
        'UKS7616072' => 'Fife',
        'UKS7616029' => 'Fife',
        'UKS7616030' => 'Fife',
        'UKS7616073' => 'Fife',
        'UKS7616031' => 'North Ayrshire',
        'UKS7616074' => 'Fife',
        'UKS7616075' => 'Highland',
        'UKS7616032' => 'East Lothian',
        'UKS7616076' => 'Moray',
        'UKS7616095' => 'Fife',
        'UKS7616077' => 'Angus',
        'UKS7616078' => 'Inverclyde',
        'UKS7616033' => 'Argyll and Bute',
        'UKS7616034' => 'Argyll and Bute',
        'UKS7616079' => 'South Ayrshire',
        'UKS7616014' => 'North Ayrshire',
        'UKS7616088' => 'Angus',
        'UKS7616035' => 'Angus',
        'UKS7616080' => 'Dumfries and Galloway',
        'UKS7616037' => 'Highland',
        'UKS7616038' => 'Highland',
        'UKS7616039' => 'East Lothian',
        'UKS7616040' => 'East Lothian',
        'UKS7616041' => 'Scottish Borders',
        'UKS7616042' => 'Aberdeenshire',
        'UKS7616044' => 'City of Edinburgh',
        'UKS7616045' => 'City of Edinburgh',
        'UKS7616046' => 'South Ayrshire',
        'UKS7616047' => 'Dumfries and Galloway',
        'UKS7616048' => 'Aberdeenshire',
        'UKS7616081' => 'Highland',
        'UKS7616049' => 'North Ayrshire',
        'UKS7616091' => 'Highland',
        'UKS7616050' => 'Dumfries and Galloway',
        'UKS7616082' => 'East Lothian',
        'UKS7616083' => 'North Ayrshire',
        'UKS7616051' => 'East Lothian',
        'UKS7616053' => 'Dumfries and Galloway',
        'UKS7616056' => 'Fife',
        'UKS7616057' => 'Fife',
        'UKS7616058' => 'Aberdeenshire',
        'UKS7616059' => 'East Lothian',
        'UKS7616085' => 'Highland',
        'UKS7616060' => 'South Ayrshire',
        'UKS7616096' => 'City of Edinburgh',
        'UKS7616062' => 'East Lothian',
        'UKS7616063' => 'East Lothian',
    ];

    /**
     * The inland sites and the loch each is on. Every other site is coastal.
     *
     * @var array<string, string>
     */
    private const LOCHS = [
        'UKS7616033' => 'Loch Lomond',
        'UKS7616015' => 'Loch Ness',
        'UKS7616075' => 'Loch Morlich',
    ];

    /**
     * @return Collection<int, BathingWaterData>
     */
    public function all(): Collection
    {
        $points = collect($this->features(self::POINTS_LAYER))
            ->filter(fn (array $feature) => isset($feature['attributes']['description'], $feature['geometry']['x'], $feature['geometry']['y']))
            ->keyBy(fn (array $feature): string => is_scalar($feature['attributes']['description']) ? (string) $feature['attributes']['description'] : '');

        return collect($this->features(self::POLYGONS_LAYER))
            ->filter(fn (array $feature) => isset($feature['attributes']['bw_id'], $feature['attributes']['description'], $feature['attributes']['bw_url']))
            ->map(function (array $feature) use ($points) {
                $attributes = $feature['attributes'];
                $point = $points->get($attributes['description']);
                [$latitude, $longitude] = $point !== null
                    ? [(float) $point['geometry']['y'], (float) $point['geometry']['x']]
                    : $this->centre($feature['geometry']['rings'] ?? []);

                if ($latitude === null || $longitude === null) {
                    return null;
                }

                return BathingWaterData::fromSepa([
                    'bw_id' => (string) $attributes['bw_id'],
                    'name' => (string) $attributes['description'],
                    'classification' => isset($attributes['class_description']) ? (string) $attributes['class_description'] : null,
                    'year' => isset($point['attributes']['year']) ? (int) $point['attributes']['year'] : null,
                    'url' => (string) $attributes['bw_url'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'district' => self::COUNCIL_AREAS[$attributes['bw_id']] ?? 'Scotland',
                    'lake' => self::LOCHS[$attributes['bw_id']] ?? null,
                ]);
            })
            ->filter()
            ->values();
    }

    /**
     * The average of a polygon's outer ring, for a site with no point yet.
     *
     * @param  array<int, mixed>  $rings
     * @return array{0: float|null, 1: float|null}
     */
    private function centre(array $rings): array
    {
        $ring = collect(is_array($rings[0] ?? null) ? $rings[0] : [])->filter(fn (mixed $vertex) => is_array($vertex) && isset($vertex[0], $vertex[1]));

        return $ring->isEmpty()
            ? [null, null]
            : [(float) $ring->avg(fn (array $vertex) => $vertex[1]), (float) $ring->avg(fn (array $vertex) => $vertex[0])];
    }

    /**
     * Every feature in a layer, in WGS84. Each layer holds under a hundred sites, well inside one page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function features(int $layer): array
    {
        $body = $this->request()
            ->get(rtrim((string) config('services.sepa.bathing_waters_url'), '/')."/{$layer}/query", [
                'where' => '1=1',
                'outFields' => '*',
                'outSR' => 4326,
                'f' => 'json',
            ])
            ->throw()
            ->json();

        if (is_array($body) && isset($body['error'])) {
            throw new RuntimeException('SEPA bathing water layer: '.($body['error']['message'] ?? 'unknown error'));
        }

        return is_array($body) && is_array($body['features'] ?? null) ? array_values(array_filter($body['features'], is_array(...))) : [];
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout(60)
            ->retry(2, 2000)
            ->withUserAgent(config('services.user_agent'));
    }
}
