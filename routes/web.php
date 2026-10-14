<?php

use App\Http\Controllers\HomeController;
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
    Route::get('/{region}/{river}', [RiverController::class, 'show'])->name('rivers.show');
    Route::get('/{region}/{river}/{spot}', [SpotController::class, 'show'])->name('spots.show');
});
