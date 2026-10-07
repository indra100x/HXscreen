<?php

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\PlaybackStat;
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
    Storage::fake('r2');

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
    expect($video->disk)->toBe('r2');
    Storage::disk('r2')->assertExists($video->url);

    $retry = $this->actingAs($user, 'sanctum')->post('/api/videos', [
        'busniss_id' => $business->id,
        'video' => UploadedFile::fake()->createWithContent('promo.mp4', $mp4Header)->mimeType('video/mp4'),
    ]);

    $retry->assertCreated();

    // Same original name in the same second must not overwrite the first file.
    expect($retry->json('video.url'))->not->toBe($video->url);
    Storage::disk('r2')->assertExists($video->url);
    Storage::disk('r2')->assertExists($retry->json('video.url'));
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

it('refuses to assign a playlist from another business to a screen', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $other = Business::create(['user_id' => $user->id, 'name' => 'Other']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-iso');

    $foreign = Playlist::create(['name' => 'Foreign', 'busniss_id' => $other->id]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $foreign->id])
        ->assertStatus(422);

    expect($screen->screenPlaylists()->count())->toBe(0);
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

it('lists only unpaired screens with live codes and no secrets', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $paired] = pairDeviceFor($this, $user, $business, 'tv-done');

    $fresh = Screen::create(['name' => 'Fresh TV', 'busniss_id' => null, 'device_id' => 'tv-fresh']);
    $fresh->pairing_code = 'ABC123';
    $fresh->pairing_code_expires_at = now()->addMinutes(10);
    $fresh->save();

    $stale = Screen::create(['name' => 'Stale TV', 'busniss_id' => null, 'device_id' => 'tv-stale']);
    $stale->pairing_code = 'OLD123';
    $stale->pairing_code_expires_at = now()->subMinute();
    $stale->save();

    $repairing = Screen::create(['name' => 'Mine Again', 'busniss_id' => $business->id, 'device_id' => 'tv-mine']);
    $repairing->pairing_code = 'NEW123';
    $repairing->pairing_code_expires_at = now()->addMinutes(10);
    $repairing->save();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/screens/unpaired')->assertOk();

    expect(collect($response->json('*.device_id'))->sort()->values()->all())->toBe(['tv-fresh', 'tv-mine'])
        ->and($response->json('0.pairing_code'))->toBeNull()
        ->and($response->json('0.device_token'))->toBeNull();
});

it('stores playback position reported with the heartbeat', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-pos');

    $video = Video::create(['busniss_id' => $business->id, 'name' => 'A', 'url' => 'videos/a.mp4']);

    $this->postJson("/api/screens/{$screen->id}/heartbeat", [
        'device_token' => $token,
        'video_id' => $video->id,
        'position_ms' => 42000,
        'is_playing' => false,
    ])->assertOk();

    $screen->refresh();

    expect($screen->current_video_id)->toBe($video->id)
        ->and($screen->current_position_ms)->toBe(42000)
        ->and($screen->is_playing)->toBeFalse()
        ->and($screen->position_reported_at)->not->toBeNull();
});

it('queues a remote command and clears it once the tv acks', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-remote');

    $this->actingAs($other, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/command", ['action' => 'pause'])
        ->assertForbidden();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/command", ['action' => 'pause'])
        ->assertOk();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/command", ['action' => 'dance'])
        ->assertStatus(422);

    $command = $this->getJson('/api/device/commands?device_token='.$token)
        ->assertOk()->json('command');

    expect($command['action'])->toBe('pause');

    $this->postJson("/api/screens/{$screen->id}/heartbeat", [
        'device_token' => $token,
        'ack_command_id' => $command['id'],
    ])->assertOk();

    $this->getJson('/api/device/commands?device_token='.$token)
        ->assertOk()->assertJsonPath('command', null);
});

it('runs the complete business to playback flow', function () {
    $user = User::factory()->create();
    $auth = function () use ($user) {
        return $this->actingAs($user, 'sanctum');
    };

    // Create business.
    $businessId = $auth()->postJson('/api/businesses', ['name' => 'Cinema'])
        ->assertCreated()->json('businesses.0.id');

    // Pair TV.
    $deviceId = 'tv-e2e';
    $code = $this->postJson('/api/screens/request-pairing-code', ['device_id' => $deviceId])
        ->assertOk()->json('pairing_code');
    $token = $auth()->postJson('/api/screens/pair', [
        'device_id' => $deviceId,
        'pairing_code' => $code,
        'busniss_id' => $businessId,
    ])->assertOk()->json('device_token');

    // Upload video.
    Storage::fake('r2');
    $mp4 = hex2bin('00000020667479706D703432000000006D70343269736F6D').str_repeat("\0", 512);
    $videoId = $auth()->post('/api/videos', [
        'busniss_id' => $businessId,
        'video' => UploadedFile::fake()->createWithContent('film.mp4', $mp4)->mimeType('video/mp4'),
    ])->assertCreated()->json('video.id');

    // Create playlist, add video, schedule it onto the paired screen.
    $playlistId = $auth()->postJson('/api/playlists', [
        'name' => 'Evening',
        'busniss_id' => $businessId,
    ])->assertCreated()->json('playlist.id');

    $auth()->postJson("/api/playlists/{$playlistId}/videos", ['video_id' => $videoId])->assertOk();

    $screenId = Screen::where('device_id', $deviceId)->firstOrFail()->id;

    $auth()->postJson("/api/screens/{$screenId}/playlists", [
        'playlist_id' => $playlistId,
        'start_time' => now()->subHour()->toDateTimeString(),
        'end_time' => now()->addHour()->toDateTimeString(),
    ])->assertOk();

    // TV downloads/plays it.
    $feed = $this->getJson('/api/device/content?device_token='.$token)->assertOk();
    expect($feed->json('playlists.0.videos.0.id'))->toBe($videoId);

    // Dashboard sees it.
    $this->actingAs($user)->get(route('business.show', $businessId))->assertOk();

    // Pause round trip.
    $auth()->postJson("/api/screens/{$screenId}/command", ['action' => 'pause'])->assertOk();
    $command = $this->getJson('/api/device/commands?device_token='.$token)->assertOk()->json('command');
    expect($command['action'])->toBe('pause');
    $this->postJson("/api/screens/{$screenId}/heartbeat", [
        'device_token' => $token,
        'ack_command_id' => $command['id'],
    ])->assertOk();
    $this->getJson('/api/device/commands?device_token='.$token)->assertOk()->assertJsonPath('command', null);

    // Unpair (new code revokes the token) and re-pair.
    $code2 = $this->postJson('/api/screens/request-pairing-code', ['device_id' => $deviceId])
        ->assertOk()->json('pairing_code');
    $this->getJson('/api/device/content?device_token='.$token)->assertUnauthorized();

    $token2 = $auth()->postJson('/api/screens/pair', [
        'device_id' => $deviceId,
        'pairing_code' => $code2,
        'busniss_id' => $businessId,
    ])->assertOk()->json('device_token');
    expect($token2)->not->toBe($token);
    $this->getJson('/api/device/content?device_token='.$token2)->assertOk();
});

it('isolates every business resource from other users', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $business = Business::create(['user_id' => $owner->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $owner, $business, 'tv-vault');
    $playlist = Playlist::create(['name' => 'Loop', 'busniss_id' => $business->id]);
    $video = Video::create(['busniss_id' => $business->id, 'name' => 'A', 'url' => 'videos/a.mp4']);
    $intruderPlaylist = Playlist::create(['name' => 'Mine', 'busniss_id' => Business::create(['user_id' => $intruder->id, 'name' => 'Rival'])->id]);

    $asIntruder = function () use ($intruder) {
        return $this->actingAs($intruder, 'sanctum');
    };

    // Screens.
    $asIntruder()->getJson("/api/screens/{$screen->id}")->assertForbidden();
    $asIntruder()->putJson("/api/screens/{$screen->id}", ['name' => 'Hijacked'])->assertForbidden();
    $asIntruder()->deleteJson("/api/screens/{$screen->id}")->assertForbidden();
    $asIntruder()->postJson("/api/screens/{$screen->id}/command", ['action' => 'pause'])->assertForbidden();
    $asIntruder()->postJson("/api/screens/{$screen->id}/playlists", ['playlist_id' => $intruderPlaylist->id])->assertForbidden();

    // Playlists.
    $asIntruder()->getJson("/api/playlists/{$playlist->id}")->assertForbidden();
    $asIntruder()->putJson("/api/playlists/{$playlist->id}", ['name' => 'Hijacked'])->assertForbidden();
    $asIntruder()->deleteJson("/api/playlists/{$playlist->id}")->assertForbidden();
    $asIntruder()->getJson("/api/playlists/{$playlist->id}/videos")->assertForbidden();
    $asIntruder()->postJson("/api/playlists/{$playlist->id}/videos", ['video_id' => $video->id])->assertForbidden();

    // Videos.
    $asIntruder()->getJson("/api/videos/{$video->id}")->assertForbidden();
    $asIntruder()->putJson("/api/videos/{$video->id}", ['name' => 'Hijacked'])->assertForbidden();
    $asIntruder()->deleteJson("/api/videos/{$video->id}")->assertForbidden();

    // Nothing changed.
    expect($screen->fresh()->name)->not->toBe('Hijacked')
        ->and($playlist->fresh()->name)->toBe('Loop')
        ->and($video->fresh()->name)->toBe('A')
        ->and(Screen::where('device_id', 'tv-vault')->exists())->toBeTrue();
});

it('rejects expired and revoked device tokens everywhere', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-token');

    // Expire the token.
    $screen->device_token_expires_at = now()->subMinute();
    $screen->save();

    $this->postJson("/api/screens/{$screen->id}/heartbeat", ['device_token' => $token])
        ->assertUnauthorized()->assertJsonPath('message', 'Device token expired');
    $this->getJson('/api/device/content?device_token='.$token)->assertUnauthorized();
    $this->getJson('/api/device/commands?device_token='.$token)->assertUnauthorized();
    $this->postJson('/api/device/refresh', ['device_token' => $token])->assertUnauthorized();

    // Revoke by requesting a fresh code.
    $screen->device_token_expires_at = now()->addDays(30);
    $screen->save();
    $this->postJson('/api/screens/request-pairing-code', ['device_id' => 'tv-token'])->assertOk();

    $this->postJson("/api/screens/{$screen->id}/heartbeat", ['device_token' => $token])
        ->assertUnauthorized()->assertJsonPath('message', 'Unauthorized');
    $this->getJson('/api/device/content?device_token='.$token)->assertUnauthorized();
});

it('streams video only through valid signed urls', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $video = Video::create(['busniss_id' => $business->id, 'disk' => 'public', 'name' => 'A', 'url' => 'videos/a.mp4']);
    Storage::disk('public')->put('videos/a.mp4', 'fake-bytes');

    $response = $this->get($video->media_url)->assertOk();
    expect($response->streamedContent())->toBe('fake-bytes');

    $this->get('/api/media/'.$video->id)->assertForbidden();
    $this->get($video->media_url.'&tampered=1')->assertForbidden();

    $expired = URL::signedRoute('media.show', ['video' => $video->id], now()->subMinute());
    $this->get($expired)->assertForbidden();
});

it('points r2 videos at presigned cloudflare urls', function () {
    Storage::fake('r2');

    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $video = Video::create(['busniss_id' => $business->id, 'name' => 'A', 'url' => 'videos/a.mp4']);
    $video->refresh();

    expect($video->disk)->toBe('r2');
    expect($video->media_url)->toContain('expiration=');
});

it('rate limits the api by default', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/businesses')->assertOk();

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('60');
});

it('switches a screen between loop and once playback', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-mode');

    expect($screen->playback_mode)->toBe('loop');

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/screens/{$screen->id}", ['playback_mode' => 'once'])
        ->assertOk();

    expect($screen->fresh()->playback_mode)->toBe('once');

    $this->actingAs($user, 'sanctum')
        ->putJson("/api/screens/{$screen->id}", ['playback_mode' => 'forever'])
        ->assertStatus(422);
});

it('accumulates play time across heartbeats without counting pauses', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-stats');

    $video = Video::create(['busniss_id' => $business->id, 'name' => 'A', 'url' => 'videos/a.mp4']);

    $beat = function (?int $position = null, bool $playing = true) use ($screen, $token, $video) {
        $payload = ['device_token' => $token, 'video_id' => $video->id];
        if ($position !== null) {
            $payload['position_ms'] = $position;
        }
        if (! $playing) {
            $payload['is_playing'] = false;
        }

        return $this->postJson("/api/screens/{$screen->id}/heartbeat", $payload)->assertOk();
    };

    $beat(0);
    $this->travel(60)->seconds();
    $beat(60_000);
    $this->travel(60)->seconds();
    $beat(120_000);
    $this->travel(60)->seconds();
    $beat(180_000, playing: false);
    $this->travel(60)->seconds();
    $beat(180_000, playing: false);

    $total = PlaybackStat::where('screen_id', $screen->id)->sum('seconds');

    // Two 60s playing intervals, then paused beats add nothing.
    expect($total)->toBe(120);
});

it('lets owners manage team members but nobody else', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $stranger = User::factory()->create();
    $business = Business::create(['user_id' => $owner->id, 'name' => 'Acme']);

    // Unknown email and non-owners are rejected.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/members", ['email' => 'ghost@example.com'])
        ->assertStatus(422);

    $this->actingAs($stranger, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/members", ['email' => $member->email])
        ->assertForbidden();

    // Invite works once, then conflicts.
    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/members", ['email' => $member->email])
        ->assertOk();

    $this->actingAs($owner, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/members", ['email' => $member->email])
        ->assertStatus(409);

    // The member can read and manage content...
    [$token, $screen] = pairDeviceFor($this, $owner, $business, 'tv-team');

    $this->actingAs($member, 'sanctum')->getJson("/api/screens/{$screen->id}")->assertOk();
    $this->actingAs($member, 'sanctum')
        ->putJson("/api/screens/{$screen->id}", ['name' => 'Lobby TV'])
        ->assertOk();

    // ...but cannot touch ownership or the team.
    $this->actingAs($member, 'sanctum')
        ->putJson("/api/businesses/{$business->id}", ['name' => 'Hijacked'])
        ->assertForbidden();
    $this->actingAs($member, 'sanctum')
        ->postJson("/api/businesses/{$business->id}/members", ['email' => $stranger->email])
        ->assertForbidden();
    $this->actingAs($member, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}")
        ->assertForbidden();

    // Owner cannot be removed; removal works.
    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}/members/{$owner->id}")
        ->assertStatus(422);

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/businesses/{$business->id}/members/{$member->id}")
        ->assertOk();

    $this->actingAs($member, 'sanctum')->getJson("/api/screens/{$screen->id}")->assertForbidden();
});

it('writes an audit trail for dashboard actions', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    [$token, $screen] = pairDeviceFor($this, $user, $business, 'tv-audit');

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/screens/{$screen->id}/command", ['action' => 'pause'])
        ->assertOk();

    $actions = AuditLog::where('busniss_id', $business->id)->pluck('action')->all();

    expect($actions)->toContain('screen.paired', 'screen.command_sent');
});
