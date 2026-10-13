<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\RiverController;
use App\Http\Controllers\SpotController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/rivers/{river}', [RiverController::class, 'show'])->name('rivers.show');
Route::get('/spots/{spot}', [SpotController::class, 'show'])->name('spots.show');
