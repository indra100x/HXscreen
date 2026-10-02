<?php

use App\Models\Business;
use App\Models\Playlist;
use App\Models\Screen;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pairDeviceFor($test, User $user, Business $business, string $deviceId = 'tv-1'): array
{
    $codeResponse = $test->postJson('/api/screens/request-pairing-code', [
        'device_id' => $deviceId,
    ]);

    $codeResponse->assertOk();

    $pairResponse = $test->actingAs($user, 'sanctum')->postJson('/api/screens/pair', [
        'device_id' => $deviceId,
        'pairing_code' => $codeResponse->json('pairing_code'),
        'busniss_id' => $business->id,
    ]);

    $pairResponse->assertOk();

    return [$pairResponse->json('device_token'), Screen::where('device_id', $deviceId)->firstOrFail()];
}

it('pairs a device and accepts the returned token on heartbeat and device content', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business);

    // The stored value must be a hash, never the raw token.
    expect($screen->device_token)->not->toBe($token);

    $this->postJson("/api/screens/{$screen->id}/heartbeat", ['device_token' => $token])
        ->assertOk()
        ->assertJsonPath('screen.id', $screen->id);

    $this->getJson('/api/device/content?device_token='.$token)
        ->assertOk()
        ->assertJsonPath('screen.id', $screen->id);

    $this->postJson("/api/screens/{$screen->id}/heartbeat", ['device_token' => 'wrong'])
        ->assertUnauthorized();
});

it('rejects creating a screen in another user’s business', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $business = Business::create(['user_id' => $owner->id, 'name' => 'Acme']);

    $this->actingAs($intruder, 'sanctum')->postJson('/api/screens', [
        'name' => 'Lobby',
        'busniss_id' => $business->id,
        'device_id' => 'tv-9',
    ])->assertForbidden();

    expect(Screen::where('device_id', 'tv-9')->exists())->toBeFalse();
});

it('assigns playlists to a screen and serves ordered videos to the device', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-2');

    $playlist = Playlist::create(['name' => 'Loop', 'busniss_id' => $business->id]);
    $first = Video::create(['busniss_id' => $business->id, 'name' => 'A', 'url' => 'videos/a.mp4']);
    $second = Video::create(['busniss_id' => $business->id, 'name' => 'B', 'url' => 'videos/b.mp4']);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/playlists/{$playlist->id}/videos", ['video_id' => $first->id])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/playlists/{$playlist->id}/videos", ['video_id' => $first->id])
        ->assertConflict();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/playlists/{$playlist->id}/videos", ['video_id' => $second->id])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/playlists/{$playlist->id}/videos/order", ['video_ids' => [$second->id, $first->id]])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $playlist->id])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $playlist->id])
        ->assertConflict();

    $content = $this->getJson('/api/device/content?device_token='.$token)->assertOk();

    expect($content->json('playlists.0.videos.*.id'))->toBe([$second->id, $first->id]);
});

it('stores an uploaded video with a name and a reachable file', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    $mp4Header = hex2bin('00000020667479706D703432000000006D70343269736F6D').str_repeat("\0", 1024);

    // Fake files report the mime from the adapter map (application/mp4), so
    // report the content mime a real upload would carry.
    $file = UploadedFile::fake()->createWithContent('promo.mp4', $mp4Header)->mimeType('video/mp4');

    $response = $this->actingAs($user, 'sanctum')->post('/api/videos', [
        'busniss_id' => $business->id,
        'video' => $file,
    ]);

    $response->assertCreated();

    $video = Video::findOrFail($response->json('video.id'));

    expect($video->name)->toBe('promo.mp4');
    Storage::disk('public')->assertExists($video->url);

    $retry = $this->actingAs($user, 'sanctum')->post('/api/videos', [
        'busniss_id' => $business->id,
        'video' => UploadedFile::fake()->createWithContent('promo.mp4', $mp4Header)->mimeType('video/mp4'),
    ]);

    $retry->assertCreated();

    // Same original name in the same second must not overwrite the first file.
    expect($retry->json('video.url'))->not->toBe($video->url);
    Storage::disk('public')->assertExists($video->url);
    Storage::disk('public')->assertExists($retry->json('video.url'));
});

it('rotates the device token on refresh and invalidates the old one', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token] = pairDeviceFor($this, $user, $business, 'tv-3');

    $refresh = $this->postJson('/api/device/refresh', ['device_token' => $token])->assertOk();

    expect($refresh->json('device_token'))->not->toBe($token);

    $this->getJson('/api/device/content?device_token='.$token)->assertUnauthorized();

    $this->getJson('/api/device/content?device_token='.$refresh->json('device_token'))->assertOk();
});

it('serves only scheduled playlists and hides device secrets', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-4');

    $active = Playlist::create(['name' => 'Now', 'busniss_id' => $business->id]);
    $expired = Playlist::create(['name' => 'Old', 'busniss_id' => $business->id]);

    $this->actingAs($user, 'sanctum')->postJson("/api/screens/{$screen->id}/playlists", [
        'playlist_id' => $active->id,
        'start_time' => now()->subHour()->toDateTimeString(),
        'end_time' => now()->addHour()->toDateTimeString(),
    ])->assertOk();

    $this->actingAs($user, 'sanctum')->postJson("/api/screens/{$screen->id}/playlists", [
        'playlist_id' => $expired->id,
        'start_time' => now()->subHours(3)->toDateTimeString(),
        'end_time' => now()->subHour()->toDateTimeString(),
    ])->assertOk();

    $content = $this->getJson('/api/device/content?device_token='.$token)->assertOk();

    expect($content->json('playlists.*.id'))->toBe([$active->id]);

    $this->actingAs($user, 'sanctum')->getJson("/api/screens/{$screen->id}")
        ->assertOk()
        ->assertJsonMissingPath('screen.device_token')
        ->assertJsonMissingPath('screen.pairing_code');
});

it('refuses to order videos from another user’s business', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $otherBusiness = Business::create(['user_id' => $other->id, 'name' => 'Rival']);

    $playlist = Playlist::create(['name' => 'Loop', 'busniss_id' => $business->id]);
    $foreign = Video::create(['busniss_id' => $otherBusiness->id, 'name' => 'X', 'url' => 'videos/x.mp4']);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/playlists/{$playlist->id}/videos/order", ['video_ids' => [$foreign->id]])
        ->assertForbidden();

    expect($playlist->videos()->count())->toBe(0);
});

it('updates a playlist schedule on a screen', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-5');

    $playlist = Playlist::create(['name' => 'Loop', 'busniss_id' => $business->id]);
    $loose = Playlist::create(['name' => 'Loose', 'busniss_id' => $business->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $playlist->id])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $loose->id])
        ->assertStatus(422);

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/screens/{$screen->id}/playlists", [
            'playlist_id' => $playlist->id,
            'start_time' => now()->subHour()->toDateTimeString(),
            'end_time' => now()->subMinute()->toDateTimeString(),
        ])
        ->assertOk();

    $content = $this->getJson('/api/device/content?device_token='.$token)->assertOk();

    expect($content->json('playlists'))->toBe([]);
});

it('lets a tv claim its token once the dashboard pairs it', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    $code = $this->postJson('/api/screens/request-pairing-code', [
        'device_id' => 'tv-claim',
    ])->assertOk()->json('pairing_code');

    $this->postJson('/api/device/claim', [
        'device_id' => 'tv-claim',
        'pairing_code' => $code,
    ])->assertOk()->assertJsonPath('paired', false);

    $this->actingAs($user, 'sanctum')->postJson('/api/screens/pair', [
        'device_id' => 'tv-claim',
        'pairing_code' => $code,
        'busniss_id' => $business->id,
    ])->assertOk();

    $token = $this->postJson('/api/device/claim', [
        'device_id' => 'tv-claim',
        'pairing_code' => $code,
    ])->assertOk()->assertJsonPath('paired', true)->json('device_token');

    expect($token)->not->toBeNull();

    $this->getJson('/api/device/content?device_token='.$token)->assertOk();
});
