<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

// Device endpoints: authenticated by pairing code / device token, not Sanctum.
Route::post('/screens/request-pairing-code', [ScreenController::class, 'requestPairingCode'])
    ->middleware('throttle:20,1')
    ->name('screens.request-code');
Route::post('/screens/{screen}/heartbeat', [ScreenController::class, 'heartbeat'])
    ->name('screens.heartbeat');
Route::post('/device/refresh', [ScreenController::class, 'refreshDeviceToken'])
    ->middleware('throttle:10,1')
    ->name('device.refresh');
Route::post('/device/claim', [ScreenController::class, 'claimDevice'])
    ->middleware('throttle:30,1')
    ->name('device.claim');
Route::get('/device/content', [ScreenController::class, 'deviceContent'])
    ->name('device.content');
Route::get('/device/commands', [ScreenController::class, 'deviceCommands'])
    ->name('device.commands');

// Signed, expiring media URLs. Deliberately outside auth and throttle:
// the signature is the capability, players fetch per range chunk, and
// NATed screens share one throttle bucket.
Route::get('/media/{video}', [MediaController::class, 'show'])
    ->middleware('signed')
    ->withoutMiddleware(ThrottleRequests::class)
    ->name('media.show');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/screens/unpaired', [ScreenController::class, 'unpaired'])
        ->name('screens.unpaired');

    Route::get('/businesses/{business}/members', [BusinessController::class, 'members'])
        ->name('businesses.members.index');
    Route::post('/businesses/{business}/members', [BusinessController::class, 'inviteMember'])
        ->name('businesses.members.store');
    Route::delete('/businesses/{business}/members/{member}', [BusinessController::class, 'removeMember'])
        ->name('businesses.members.destroy');

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
