<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\PhishingLogController;
use Illuminate\Support\Facades\Route;

Route::post('/phishing-logs', [PhishingLogController::class, 'store'])
    ->middleware('throttle:120,1')
    ->name('phishing-logs.store');

Route::post('/devices/register', [DeviceController::class, 'register'])
    ->middleware('throttle:120,1')
    ->name('devices.register');
