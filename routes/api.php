<?php

use App\Http\Controllers\Api\AreaSummaryController;
use App\Http\Controllers\Api\CrimeComparisonController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

Route::get('/search', SearchController::class);
Route::get('/area-summary', AreaSummaryController::class);
Route::get('/crime-comparison', CrimeComparisonController::class);
