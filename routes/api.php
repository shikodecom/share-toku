<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DistributionController;
use App\Http\Controllers\SiteConnectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/site-connections/exchange', [SiteConnectionController::class, 'exchange'])->middleware('throttle:30,1');
    Route::get('/site/me', [DistributionController::class, 'me'])->middleware(['throttle:site', 'site.token:site:read']);
    Route::post('/site/disconnect', [SiteConnectionController::class, 'disconnect'])->middleware(['throttle:site', 'site.token:site:read']);
    Route::get('/site/offers', [DistributionController::class, 'offers'])->middleware(['throttle:site', 'site.token:placements:read']);
    Route::post('/placements/resolve', [DistributionController::class, 'resolve'])->middleware(['throttle:site', 'site.token:placements:read']);
    Route::post('/events/batch', [AnalyticsController::class, 'batch'])->middleware('throttle:120,1');
});
