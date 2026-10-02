<?php

use App\Http\Controllers\ScreenController;
use App\Models\Business;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

it('requests a pairing code and pairs a tv device to a business', function () {
    $user = User::factory()->create();
    $business = Business::create([
        'user_id' => $user->id,
        'name' => 'Test Business',
    ]);

    $deviceId = (string) Str::uuid();

    $request = new Request([
        'device_id' => $deviceId,
        'name' => 'Living Room TV',
    ]);

    $response = app(ScreenController::class)->requestPairingCode($request);
    $responseData = $response->getData(true);

    expect($response->status())->toBe(200)
        ->and($responseData['device_id'])->toBe($deviceId)
        ->and($responseData['pairing_code'])->not->toBeNull()
        ->and($responseData['expires_in_seconds'])->toBe(600);

    $screen = Screen::where('device_id', $deviceId)->firstOrFail();

    $pairRequest = new Request([
        'device_id' => $deviceId,
        'pairing_code' => $responseData['pairing_code'],
        'busniss_id' => $business->id,
    ]);
    $pairRequest->setUserResolver(fn () => $user);

    $pairResponse = app(ScreenController::class)->pair($pairRequest);
    $pairResponseData = $pairResponse->getData(true);

    expect($pairResponse->status())->toBe(200)
        ->and($pairResponseData['device_token'])->not->toBeNull()
        ->and($pairResponseData['busniss_id'])->toBe($business->id);

    $screen->refresh();

    expect($screen->busniss_id)->toBe($business->id)
        ->and($screen->device_token)->not->toBeNull()
        ->and($screen->pairing_code)->toBeNull();
});
