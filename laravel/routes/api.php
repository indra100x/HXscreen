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
Route::post('/device/claim', [ScreenController::class, 'claimDevice'])
    ->middleware('throttle:30,1')
    ->name('device.claim');
Route::get('/device/content', [ScreenController::class, 'deviceContent'])
    ->middleware('throttle:120,1')
    ->name('device.content');
Route::get('/device/commands', [ScreenController::class, 'deviceCommands'])
    ->middleware('throttle:120,1')
    ->name('device.commands');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/screens/unpaired', [ScreenController::class, 'unpaired'])
        ->name('screens.unpaired');

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
    Route::put('/screens/{screen}/playlists', [ScreenController::class, 'updatePlaylistSchedule'])
        ->name('screens.playlists.update');
    Route::delete('/screens/{screen}/playlists', [ScreenController::class, 'detachPlaylist'])
        ->name('screens.playlists.destroy');
    Route::post('/screens/{screen}/command', [ScreenController::class, 'sendCommand'])
        ->name('screens.command');

    Route::get('/playlists/{playlist}/videos', [PlaylistController::class, 'getVideos'])
        ->name('playlists.videos.index');
    Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo'])
        ->name('playlists.videos.store');
    Route::delete('/playlists/{playlist}/videos', [PlaylistController::class, 'removeVideo'])
        ->name('playlists.videos.destroy');
    Route::put('/playlists/{playlist}/videos/order', [PlaylistController::class, 'orderVideos'])
        ->name('playlists.videos.order');
});
