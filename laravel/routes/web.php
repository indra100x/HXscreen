<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('screens', [DashboardController::class, 'screens'])->name('screens.overview');
    Route::get('storage', [DashboardController::class, 'storage'])->name('storage');
    Route::get('businesses/{business}', [DashboardController::class, 'show'])->name('business.show');
    Route::get('businesses/{business}/analytics', [DashboardController::class, 'analytics'])->name('business.analytics');
});

require __DIR__.'/settings.php';
