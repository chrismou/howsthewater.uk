<?php

use App\Http\Controllers\BeachController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LakeController;
use App\Http\Controllers\LegacyRiverRedirectController;
use App\Http\Controllers\OverflowController;
use App\Http\Controllers\PlaceHubController;
use App\Http\Controllers\PlaceSpillsController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RiverController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpillsController;
use App\Http\Controllers\SpotController;
use App\Http\Controllers\SwimmingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Pages are the same for every visitor (no cookies or accounts), so Cloudflare may keep them for five minutes and
// browsers for one, and serve a copy up to five minutes older while it fetches a new one. Data changes every 15
// minutes at most, and only successful responses are marked cacheable.
$pageCaching = 'cache.headers:public;max_age=60;s_maxage=300;stale_while_revalidate=300';

Route::middleware($pageCaching)->group(function () {
    Route::get('/', HomeController::class)->name('home');

    // These come before the region route, which would otherwise take them as region slugs.
    // Region::RESERVED_SLUGS lists these slugs, which no region may take.
    Route::get('/rivers', [PlaceHubController::class, 'rivers'])->name('hubs.rivers');
    Route::get('/beaches', [PlaceHubController::class, 'beaches'])->name('hubs.beaches');
    Route::get('/lakes', [PlaceHubController::class, 'lakes'])->name('hubs.lakes');
    Route::view('/about', 'about')->name('about');
    Route::view('/privacy', 'privacy')->name('privacy');
    Route::view('/terms', 'terms')->name('terms');
    Route::get('/spills', [SpillsController::class, 'index'])->name('spills.index');
    Route::get('/swimming', SwimmingController::class)->name('swimming');
    Route::get('/swimming/places.json', [SwimmingController::class, 'places'])->name('swimming.places');
    Route::get('/overflows/{monitor}', [OverflowController::class, 'show'])->name('overflows.show');
});

// An overflow's feed sits beside its page, rate limited and cached like the place feeds.
Route::get('/overflows/{monitor}/feed', [FeedController::class, 'overflow'])->name('overflows.feed')->middleware(['throttle:public', 'cache.headers:public;max_age=300']);

// More rows for /spills' long lists, which show their first rows only.
Route::get('/spills/rows/{list}', [SpillsController::class, 'rows'])->whereIn('list', ['discharging', 'near', 'recent'])->name('spills.rows')->middleware(['throttle:public', 'cache.headers:public;max_age=60;s_maxage=300']);

// Each query is its own URL, so search results are never stored: the edge would otherwise keep one copy per query.
Route::get('/search', SearchController::class)->name('search')->middleware(['throttle:public', 'cache.headers:no_store;private']);

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap')->middleware('cache.headers:public;max_age=3600');

// Places that moved region or were renamed, with their spills pages and feeds, before the routes that would 404 them.
$movedPlaces = [
    '/thames-valley/lakes/hampstead-heath-ponds' => '/london/lakes/hampstead-heath',
    '/thames-valley/lakes/the-serpentine' => '/london/lakes/the-serpentine',
    '/south-west/beaches/paignton-paignton-sands' => '/south-west/beaches/paignton-sands',
];

foreach ($movedPlaces as $from => $to) {
    foreach (['', '/spills', '/feed'] as $suffix) {
        Route::permanentRedirect($from.$suffix, $to.$suffix);
    }
}

// Rivers, beaches and lakes are scoped to their region, since names repeat across Britain. The region page is an
// overview linking to each.
Route::scopeBindings()->group(function () use ($pageCaching) {
    Route::middleware($pageCaching)->group(function () {
        Route::get('/{region}', [RegionController::class, 'show'])->name('regions.show');
        Route::get('/{region}/spills', [SpillsController::class, 'region'])->name('spills.region');

        Route::get('/{region}/rivers', [RiverController::class, 'index'])->name('rivers.index');
        Route::get('/{region}/beaches', [BeachController::class, 'index'])->name('beaches.index');
        Route::get('/{region}/beaches/{beach}', [BeachController::class, 'show'])->name('beaches.show');
        Route::get('/{region}/lakes', [LakeController::class, 'index'])->name('lakes.index');
        Route::get('/{region}/lakes/{lake}', [LakeController::class, 'show'])->name('lakes.show');
        // Not rate limited, so crawlers can read every place's history; each place's figures are cached
        // (PlaceSpillsService), so URLs that only differ by query string cost little.
        Route::get('/{region}/beaches/{beach}/spills', [PlaceSpillsController::class, 'beach'])->name('beaches.spills');
        Route::get('/{region}/lakes/{lake}/spills', [PlaceSpillsController::class, 'lake'])->name('lakes.spills');
        Route::get('/{region}/rivers/{river}/{spot}/spills', [PlaceSpillsController::class, 'spot'])->name('spots.spills');
    });

    // Feeds (an overflow's feed sits at the top, outside the region routes) and the spot's extra overflow rows come
    // before the spot route, which would otherwise take "feed" or
    // "monitors" as a spot slug.
    Route::middleware(['throttle:public', 'cache.headers:public;max_age=300'])->group(function () {
        Route::get('/{region}/beaches/{beach}/feed', [FeedController::class, 'beach'])->name('beaches.feed');
        Route::get('/{region}/lakes/{lake}/feed', [FeedController::class, 'lake'])->name('lakes.feed');
        Route::get('/{region}/rivers/{river}/feed', [FeedController::class, 'river'])->name('rivers.feed');
        Route::get('/{region}/rivers/{river}/{spot}/feed', [FeedController::class, 'spot'])->name('spots.feed');
        Route::get('/{region}/rivers/{river}/{spot}/monitors', [SpotController::class, 'monitors'])->name('spots.monitors');
        // More rows for the spills pages' long lists, which show their first rows only.
        Route::get('/{region}/spills/rows/{list}', [SpillsController::class, 'regionRows'])->whereIn('list', ['discharging', 'near', 'recent'])->name('spills.region.rows');
    });

    // A river listed under another region but running through this one (the Thames through London) redirects there.
    $riverElsewhere = fn (Request $request) => app(LegacyRiverRedirectController::class)->riverElsewhere($request);

    Route::middleware($pageCaching)->group(function () use ($riverElsewhere) {
        Route::get('/{region}/rivers/{river}', [RiverController::class, 'show'])->name('rivers.show')->missing($riverElsewhere);
        Route::get('/{region}/rivers/{river}/{spot}', [SpotController::class, 'show'])->name('spots.show')->missing($riverElsewhere);
    });

    // Rivers and spots used to sit straight under the region. These come last, so every route above wins, and an
    // old address for a river or spot that no longer exists still 404s.
    Route::get('/{region}/{river}/feed', [LegacyRiverRedirectController::class, 'riverFeed']);
    Route::get('/{region}/{river}/{spot}/feed', [LegacyRiverRedirectController::class, 'spotFeed']);
    Route::get('/{region}/{river}', [LegacyRiverRedirectController::class, 'river'])->missing($riverElsewhere);
    Route::get('/{region}/{river}/{spot}', [LegacyRiverRedirectController::class, 'spot'])->missing($riverElsewhere);
});
