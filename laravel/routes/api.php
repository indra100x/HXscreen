<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Device endpoints: authenticated by pairing code / device token, not Sanctum.
Route::post('/screens/request-pairing-code', [ScreenController::class, 'requestPairingCode'])
    ->middleware('throttle:20,1')
    ->name('screens.request-code');
Route::post('/screens/{screen}/heartbeat', [ScreenController::class, 'heartbeat'])
    ->middleware('throttle:120,1')
    ->name('screens.heartbeat');
Route::post('/device/refresh', [ScreenController::class, 'refreshDeviceToken'])
    ->middleware('throttle:10,1')
    ->name('device.refresh');
Route::get('/device/content', [ScreenController::class, 'deviceContent'])
    ->middleware('throttle:120,1')
    ->name('device.content');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::apiResources([
        'businesses' => BusinessController::class,
        'screens' => ScreenController::class,
        'playlists' => PlaylistController::class,
        'videos' => VideoController::class,
    ]);

    Route::post('/screens/pair', [ScreenController::class, 'pair'])
        ->middleware('throttle:10,1')
        ->name('screens.pair');

    Route::get('/screens/{screen}/playlists', [ScreenController::class, 'getPlaylists'])
        ->name('screens.playlists.index');
    Route::post('/screens/{screen}/playlists', [ScreenController::class, 'attachPlaylist'])
        ->name('screens.playlists.store');
    Route::delete('/screens/{screen}/playlists', [ScreenController::class, 'detachPlaylist'])
        ->name('screens.playlists.destroy');

    Route::get('/playlists/{playlist}/videos', [PlaylistController::class, 'getVideos'])
        ->name('playlists.videos.index');
    Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo'])
        ->name('playlists.videos.store');
    Route::delete('/playlists/{playlist}/videos', [PlaylistController::class, 'removeVideo'])
        ->name('playlists.videos.destroy');
    Route::put('/playlists/{playlist}/videos/order', [PlaylistController::class, 'orderVideos'])
        ->name('playlists.videos.order');
});
