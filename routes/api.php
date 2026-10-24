<?php

use App\Http\Controllers\PhishingLogController;
use Illuminate\Support\Facades\Route;

Route::post('/phishing-logs', [PhishingLogController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('phishing-logs.store');
