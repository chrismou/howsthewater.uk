<?php

use App\Http\Controllers\BeachController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LakeController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\RiverController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpotController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap')->middleware('cache.headers:public;max_age=3600');

// Rivers and spots are scoped to their region, since names repeat across Britain.
Route::scopeBindings()->group(function () {
    Route::get('/{region}', [RegionController::class, 'show'])->name('regions.show');

    // Beaches and lakes come before rivers, which would otherwise take "beaches" or "lakes" as a river slug.
    Route::get('/{region}/beaches', [BeachController::class, 'index'])->name('beaches.index');
    Route::get('/{region}/beaches/{beach}', [BeachController::class, 'show'])->name('beaches.show');
    Route::get('/{region}/lakes', [LakeController::class, 'index'])->name('lakes.index');
    Route::get('/{region}/lakes/{lake}', [LakeController::class, 'show'])->name('lakes.show');

    Route::get('/{region}/{river}', [RiverController::class, 'show'])->name('rivers.show');

    // Feeds come before the spot route, which would otherwise take "feed" as a spot slug.
    Route::middleware('cache.headers:public;max_age=300')->group(function () {
        Route::get('/{region}/{river}/feed', [FeedController::class, 'river'])->name('rivers.feed');
        Route::get('/{region}/{river}/{spot}/feed', [FeedController::class, 'spot'])->name('spots.feed');
    });

    Route::get('/{region}/{river}/{spot}', [SpotController::class, 'show'])->name('spots.show');
});
