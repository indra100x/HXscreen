<?php

use App\Http\Controllers\BusinessController;
use App\Http\Controllers\PlaylistController;
use App\Http\Controllers\ScreenController;
use App\Http\Controllers\VideoController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

    Route::post('/screens/request-pairing-code', [ScreenController::class, 'requestPairingCode']);
    Route::post('/screens/pair', [ScreenController::class, 'pair']);
    Route::post('/screens/{screen}/heartbeat', [ScreenController::class, 'heartbeat']);

    Route::get('/playlists/{playlist}/videos', [PlaylistController::class, 'getVideos']);
    Route::post('/playlists/{playlist}/videos', [PlaylistController::class, 'addVideo']);
    Route::delete('/playlists/{playlist}/videos', [PlaylistController::class, 'removeVideo']);
    Route::put('/playlists/{playlist}/videos/order', [PlaylistController::class, 'orderVideos']);
});
