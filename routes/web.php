<?php

use App\Http\Controllers\BeachController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LakeController;
use App\Http\Controllers\LegacyRiverRedirectController;
use App\Http\Controllers\PlaceHubController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RiverController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpotController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search')->middleware('throttle:public');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap')->middleware('cache.headers:public;max_age=3600');

// These come before the region route, which would otherwise take them as region slugs.
// Region::RESERVED_SLUGS lists these slugs, which no region may take.
Route::get('/rivers', [PlaceHubController::class, 'rivers'])->name('hubs.rivers');
Route::get('/beaches', [PlaceHubController::class, 'beaches'])->name('hubs.beaches');
Route::get('/lakes', [PlaceHubController::class, 'lakes'])->name('hubs.lakes');
Route::view('/about', 'about')->name('about');
Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');

// Rivers, beaches and lakes are scoped to their region, since names repeat across Britain. The region page is an
// overview linking to each.
Route::scopeBindings()->group(function () {
    Route::get('/{region}', [RegionController::class, 'show'])->name('regions.show');

    Route::get('/{region}/rivers', [RiverController::class, 'index'])->name('rivers.index');
    Route::get('/{region}/beaches', [BeachController::class, 'index'])->name('beaches.index');
    Route::get('/{region}/beaches/{beach}', [BeachController::class, 'show'])->name('beaches.show');
    Route::get('/{region}/lakes', [LakeController::class, 'index'])->name('lakes.index');
    Route::get('/{region}/lakes/{lake}', [LakeController::class, 'show'])->name('lakes.show');

    // Feeds and the spot's extra overflow rows come before the spot route, which would otherwise take "feed" or
    // "monitors" as a spot slug.
    Route::middleware(['throttle:public', 'cache.headers:public;max_age=300'])->group(function () {
        Route::get('/{region}/beaches/{beach}/feed', [FeedController::class, 'beach'])->name('beaches.feed');
        Route::get('/{region}/lakes/{lake}/feed', [FeedController::class, 'lake'])->name('lakes.feed');
        Route::get('/{region}/rivers/{river}/feed', [FeedController::class, 'river'])->name('rivers.feed');
        Route::get('/{region}/rivers/{river}/{spot}/feed', [FeedController::class, 'spot'])->name('spots.feed');
        Route::get('/{region}/rivers/{river}/{spot}/monitors', [SpotController::class, 'monitors'])->name('spots.monitors');
    });

    Route::get('/{region}/rivers/{river}', [RiverController::class, 'show'])->name('rivers.show');

    Route::get('/{region}/rivers/{river}/{spot}', [SpotController::class, 'show'])->name('spots.show');

    // Rivers and spots used to sit straight under the region. These come last, so every route above wins, and an
    // old address for a river or spot that no longer exists still 404s.
    Route::get('/{region}/{river}/feed', [LegacyRiverRedirectController::class, 'riverFeed']);
    Route::get('/{region}/{river}/{spot}/feed', [LegacyRiverRedirectController::class, 'spotFeed']);
    Route::get('/{region}/{river}', [LegacyRiverRedirectController::class, 'river']);
    Route::get('/{region}/{river}/{spot}', [LegacyRiverRedirectController::class, 'spot']);
});
