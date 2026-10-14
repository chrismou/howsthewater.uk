<?php

use App\Http\Controllers\Api\V1\BeachController;
use App\Http\Controllers\Api\V1\LakeController;
use App\Http\Controllers\Api\V1\RegionController;
use App\Http\Controllers\Api\V1\RiverController;
use App\Http\Controllers\Api\V1\SpotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['throttle:public', 'cache.headers:public;max_age=300;etag'])->scopeBindings()->group(function () {
    Route::get('regions', [RegionController::class, 'index'])->name('regions.index');
    Route::get('regions/{region}', [RegionController::class, 'show'])->name('regions.show');
    Route::get('regions/{region}/rivers', [RiverController::class, 'index'])->name('rivers.index');
    Route::get('regions/{region}/rivers/{river}', [RiverController::class, 'show'])->name('rivers.show');
    Route::get('regions/{region}/spots', [SpotController::class, 'index'])->name('spots.index');
    Route::get('regions/{region}/rivers/{river}/spots/{spot}', [SpotController::class, 'show'])->name('spots.show');
    Route::get('regions/{region}/beaches', [BeachController::class, 'index'])->name('beaches.index');
    Route::get('regions/{region}/beaches/{beach}', [BeachController::class, 'show'])->name('beaches.show');
    Route::get('regions/{region}/lakes', [LakeController::class, 'index'])->name('lakes.index');
    Route::get('regions/{region}/lakes/{lake}', [LakeController::class, 'show'])->name('lakes.show');
});
