<?php

use App\Http\Controllers\Api\V1\RiverController;
use App\Http\Controllers\Api\V1\SpotController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('rivers', [RiverController::class, 'index'])->name('rivers.index');
    Route::get('rivers/{river}', [RiverController::class, 'show'])->name('rivers.show');
    Route::get('spots', [SpotController::class, 'index'])->name('spots.index');
    Route::get('spots/{spot}', [SpotController::class, 'show'])->name('spots.show');
});
