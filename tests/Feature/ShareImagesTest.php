<?php

use App\Models\BathingWater;
use App\Models\Lake;
use App\Models\OverflowMonitor;
use App\Models\Region;
use App\Models\River;
use App\Models\ShareImage;
use App\Models\Spot;
use App\Services\ShareImages\ShareCards;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: Region, 1: River, 2: Spot}
 */
function shareablePlaces(): array
{
    $region = Region::factory()->create(['name' => 'Yorkshire', 'slug' => 'yorkshire']);
    $river = River::factory()->withCentreline()->create(['region_id' => $region->id, 'name' => 'River Wharfe', 'slug' => 'wharfe']);
    $spot = Spot::factory()->create(['river_id' => $river->id, 'name' => 'Ilkley', 'slug' => 'ilkley', 'latitude' => 53.9, 'longitude' => -1.8]);

    return [$region, $river, $spot];
}

it('draws a 1200 by 630 card for every spot, river, beach and lake page, and nothing for places without one', function () {
    Storage::fake('public');
    [$region, $river, $spot] = shareablePlaces();
    Spot::factory()->create(['river_id' => $river->id, 'is_published' => false]);
    $tributary = River::factory()->withCentreline()->create(['region_id' => $region->id, 'parent_river_id' => $river->id]);
    Spot::factory()->create(['river_id' => $tributary->id]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id]);
    $lake = Lake::factory()->create(['region_id' => $region->id]);

    $this->artisan('share-images:generate')->assertSuccessful()->expectsOutputToContain('Drew 4 share cards, 0 unchanged, 0 removed.');

    foreach ([$spot, $river, $beach, $lake] as $place) {
        $image = $place->shareImage;
        expect($image)->not->toBeNull();
        Storage::disk('public')->assertExists($image->path);
        expect(getimagesizefromstring(Storage::disk('public')->get($image->path)))->toMatchArray([0 => 1200, 1 => 630]);
    }
    expect($spot->shareImage->alt)->toBe('Ilkley on the River Wharfe, Yorkshire, marked on a map of the river from source to mouth')
        ->and($spot->shareImage->path)->toStartWith("share/spots/{$spot->id}-");
});

it('redraws only cards whose content changed, and keeps a superseded file for a day', function () {
    Storage::fake('public');
    [, $river, $spot] = shareablePlaces();

    $this->artisan('share-images:generate')->expectsOutputToContain('Drew 2 share cards');
    $this->artisan('share-images:generate')->expectsOutputToContain('Drew 0 share cards, 2 unchanged');

    $old = $spot->shareImage->path;
    $riverCard = $river->shareImage->path;
    $spot->update(['name' => 'Ilkley Lido']);

    $this->artisan('share-images:generate')->expectsOutputToContain('Drew 1 share cards, 1 unchanged');

    expect($spot->fresh()->shareImage->path)->not->toBe($old)
        ->and($river->fresh()->shareImage->path)->toBe($riverCard);
    Storage::disk('public')->assertExists($old);

    touch(Storage::disk('public')->path($old), now()->subDays(2)->getTimestamp());
    $this->artisan('share-images:generate');

    Storage::disk('public')->assertMissing($old);
});

it('forgets the card of a place that no longer has a page', function () {
    Storage::fake('public');
    [, , $spot] = shareablePlaces();
    $this->artisan('share-images:generate');

    $spot->update(['is_published' => false]);

    $this->artisan('share-images:generate')->expectsOutputToContain('1 removed');
    expect(ShareImage::query()->where('shareable_type', Spot::class)->exists())->toBeFalse();
});

it('never mentions spills on cards where the region has no overflow data', function () {
    [, $river, $spot] = shareablePlaces();
    $wales = Region::factory()->create(['name' => 'Wales', 'slug' => 'wales', 'overflow_feeds' => []]);
    $cards = app(ShareCards::class);

    expect($cards->forSpot($spot, $river, $river->region, collect([$spot]))->tagline)->toContain('spills')
        ->and($cards->forSpot($spot, $river, $wales, collect([$spot]))->tagline)->not->toContain('spills')
        ->and($cards->forBeach(BathingWater::factory()->make(), $wales)->tagline)->not->toContain('spills');
});

it('shares the page\'s own card, or the site card until it is drawn', function () {
    Storage::fake('public');
    [$region, $river, $spot] = shareablePlaces();
    $url = route('spots.show', [$region, $river, $spot]);

    $this->get($url)->assertSee('<meta property="og:image" content="'.asset('og-image.png').'">', false);

    $this->artisan('share-images:generate');
    $image = $spot->fresh()->shareImage;

    $this->get($url)
        ->assertSee('<meta property="og:image" content="'.$image->url().'">', false)
        ->assertSee('<meta name="twitter:image" content="'.$image->url().'">', false)
        ->assertSee('content="Ilkley on the River Wharfe, Yorkshire, marked on a map of the river from source to mouth"', false);
    $this->get(route('rivers.show', [$region, $river]))->assertSee($river->fresh()->shareImage->url(), false);
});

it('draws overflow cards in batches on their own run, which the place run leaves alone', function () {
    Storage::fake('public');
    [$region, $river] = shareablePlaces();
    $monitors = OverflowMonitor::factory()->count(3)->create(['river_id' => $river->id, 'river_metres' => 1000]);
    $monitors[0]->update(['return_name' => 'Ellerton CSO']);
    $unshown = OverflowMonitor::factory()->create(['river_id' => null]);

    $this->artisan('share-images:generate --overflows --limit=2')->expectsOutputToContain('Drew 2 overflow share cards, 0 unchanged, 0 removed, 1 waiting')->assertSuccessful();
    $this->artisan('share-images:generate --overflows --limit=2')->expectsOutputToContain('Drew 1 overflow share cards, 2 unchanged');
    $this->artisan('share-images:generate')->expectsOutputToContain('0 removed');

    $card = $monitors[0]->fresh()->shareImage;

    expect($card->path)->toStartWith("share/overflows/{$monitors[0]->id}-")
        ->and($card->alt)->toBe('Ellerton CSO, a storm overflow into the River Wharfe, Yorkshire, marked on a map of the river from source to mouth')
        ->and($unshown->shareImage()->exists())->toBeFalse();
    Storage::disk('public')->assertExists($card->path);
    $this->get(route('overflows.show', $monitors[0]))->assertSee('<meta property="og:image" content="'.$card->url().'">', false);
});

it('redraws every overflow card when forced, and pins one with no river on Britain under its place\'s region', function () {
    Storage::fake('public');
    [$region, $river] = shareablePlaces();
    OverflowMonitor::factory()->count(3)->create(['river_id' => $river->id, 'river_metres' => 1000]);
    $beach = BathingWater::factory()->create(['region_id' => $region->id, 'latitude' => 54.28, 'longitude' => -0.40]);
    $nearBeach = OverflowMonitor::factory()->create(['external_id' => 'YWS07777', 'river_id' => null, 'receiving_watercourse' => 'North Sea', 'latitude' => 54.285, 'longitude' => -0.40]);
    app(\App\Actions\Ingest\RefreshRegions::class)->handle([$region->id]);

    $this->artisan('share-images:generate --overflows --limit=1')->expectsOutputToContain('Drew 1 overflow share cards');
    $this->artisan('share-images:generate --overflows --limit=1 --force')->expectsOutputToContain('Drew 4 overflow share cards, 0 unchanged, 0 removed, 0 waiting');

    expect($nearBeach->fresh()->shareImage->alt)->toBe('Storm overflow YWS07777 into North Sea, Yorkshire, marked on a map of Britain');
});
