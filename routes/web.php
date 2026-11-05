<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Middleware\EnsureStaticAdminAuthenticated;
use Illuminate\Support\Facades\Route;

Route::get('/', [AdminController::class, 'home'])->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', EnsureStaticAdminAuthenticated::class])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');
        Route::delete('/logs', [AdminController::class, 'deleteLogs'])->name('logs.delete-all');
        Route::delete('/logs/{log}', [AdminController::class, 'deleteLog'])->whereNumber('log')->name('logs.destroy');
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::post('/alerts/read-all', [AdminController::class, 'markAllAlertsRead'])->name('alerts.read-all');
        Route::post('/alerts/{alert}/read', [AdminController::class, 'markAlertRead'])->name('alerts.read');
        Route::delete('/alerts', [AdminController::class, 'deleteAllAlerts'])->name('alerts.delete-all');
        Route::get('/alerts', [AdminController::class, 'alerts'])->name('alerts');
        Route::get('/campuses', [AdminController::class, 'campuses'])->name('campuses');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
    });
