<?php

namespace App\Services;

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\Region;
use App\Models\River;
use App\Models\Spot;

/**
 * Schema.org descriptions of the site and its places, for pages to embed as
 * JSON-LD through the layout's structuredData, which joins them with the
 * page's WebPage and BreadcrumbList into one @graph. Each place's @id is its
 * page URL with a fragment, so it is the same wherever the place appears. They state only what the page
 * does (names, places, coordinates and links), never a rating or a claim
 * about the water, which the site never makes.
 */
class StructuredData
{
    /**
     * The site and who runs it, for the home page. The search action points
     * at the site's own search.
     *
     * @return list<array<string, mixed>>
     */
    public function site(string $description): array
    {
        $home = rtrim(route('home'), '/').'/';

        return [[
            '@graph' => [
                [
                    '@type' => 'WebSite',
                    '@id' => $home.'#website',
                    'url' => $home,
                    'name' => config('app.name'),
                    'description' => $description,
                    'inLanguage' => 'en-GB',
                    'publisher' => ['@id' => $home.'#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('search').'?q={search_term_string}'],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'Organization',
                    '@id' => $home.'#organization',
                    'name' => config('app.name'),
                    'url' => $home,
                    'logo' => asset('apple-touch-icon.png'),
                    'founder' => ['@type' => 'Person', 'name' => 'Chris Chrisostomou', 'url' => 'https://mou.me'],
                    'sameAs' => ['https://github.com/chrismou/howsthewater.uk'],
                ],
            ],
        ]];
    }

    /**
     * A swimming, paddling or fishing spot: a place on its river, in its region.
     *
     * @return list<array<string, mixed>>
     */
    public function spot(Spot $spot, River $river, Region $region): array
    {
        $url = route('spots.show', [$region, $river, $spot]);

        return [[
            '@type' => 'Place',
            '@id' => $url.'#place',
            'name' => $spot->name.', '.$river->name,
            'url' => $url,
            'geo' => $this->geo($spot->latitude, $spot->longitude),
            'containedInPlace' => $this->river($river, $region),
        ]];
    }

    /**
     * A river: the body of water, in its region.
     *
     * @return list<array<string, mixed>>
     */
    public function riverPage(River $river, Region $region): array
    {
        return [$this->river($river, $region)];
    }

    /**
     * A designated bathing beach, with its council area as the address region.
     *
     * @return list<array<string, mixed>>
     */
    public function beach(BathingWater $beach, Region $region): array
    {
        $url = route('beaches.show', [$region, $beach]);

        return [array_filter([
            '@type' => 'Beach',
            '@id' => $url.'#beach',
            'name' => $beach->name,
            'url' => $url,
            'geo' => $this->geo($beach->latitude, $beach->longitude),
            'address' => $beach->district !== null ? ['@type' => 'PostalAddress', 'addressRegion' => $beach->district, 'addressCountry' => 'GB'] : null,
            'containedInPlace' => $this->region($region),
        ])];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lake(Lake $lake, Region $region): array
    {
        $url = route('lakes.show', [$region, $lake]);

        return [[
            '@type' => 'LakeBodyOfWater',
            '@id' => $url.'#lake',
            'name' => $lake->name,
            'url' => $url,
            'geo' => $this->geo($lake->latitude, $lake->longitude),
            'containedInPlace' => $this->region($region),
        ]];
    }

    /**
     * A region page: the region as the Place every spot, beach and lake page names as containedInPlace, then its
     * listings.
     *
     * @param  iterable<array{0: string, 1: string}>  $listings  Name and URL pairs
     * @return list<array<string, mixed>>
     */
    public function regionPage(Region $region, iterable $listings): array
    {
        return [$this->region($region), ...$this->itemList($region->name, $listings)];
    }

    /**
     * The places a listing page links to, in the order shown.
     *
     * @param  iterable<array{0: string, 1: string}>  $items  [name, url] pairs
     * @return list<array<string, mixed>>
     */
    public function itemList(string $name, iterable $items): array
    {
        $elements = [];

        foreach ($items as [$itemName, $url]) {
            $elements[] = ['@type' => 'ListItem', 'position' => count($elements) + 1, 'name' => $itemName, 'url' => $url];
        }

        return [['@type' => 'ItemList', 'name' => $name, 'numberOfItems' => count($elements), 'itemListElement' => $elements]];
    }

    /**
     * @return array<string, mixed>
     */
    private function river(River $river, Region $region): array
    {
        $url = route('rivers.show', [$region, $river]);

        return [
            '@type' => 'RiverBodyOfWater',
            '@id' => $url.'#river',
            'name' => $river->name,
            'url' => $url,
            'containedInPlace' => $this->region($region),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function region(Region $region): array
    {
        $url = route('regions.show', $region);

        return ['@type' => 'Place', '@id' => $url.'#region', 'name' => $region->name, 'url' => $url];
    }

    /**
     * @return array<string, mixed>
     */
    private function geo(float $latitude, float $longitude): array
    {
        return ['@type' => 'GeoCoordinates', 'latitude' => round($latitude, 6), 'longitude' => round($longitude, 6)];
    }
}
