<?php

use App\Models\Business;
use App\Models\PlaybackStat;
use App\Models\Screen;
use App\Models\User;
use App\Models\Video;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('owners can visit their business page', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $this->actingAs($user);

    $response = $this->get(route('business.show', $business));
    $response->assertOk();
});

test('users cannot visit another user’s business page', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $business = Business::create(['user_id' => $owner->id, 'name' => 'Acme']);
    $this->actingAs($intruder);

    $response = $this->get(route('business.show', $business));
    $response->assertForbidden();
});

test('a session-authenticated browser can write to the api like the dashboard does', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
    $this->assertAuthenticated();

    $response = $this->withHeader('Referer', 'http://127.0.0.1:8000/dashboard')
        ->postJson('/api/businesses', ['name' => 'Acme']);

    $response->assertCreated();
});

test('owners see their business analytics but not others’', function () {
    $user = User::factory()->create();
    $intruder = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);
    $screen = Screen::create(['busniss_id' => $business->id, 'name' => 'Lobby', 'device_id' => 'tv-1']);
    $video = Video::create(['busniss_id' => $business->id, 'name' => 'Intro', 'url' => 'videos/intro.mp4']);

    PlaybackStat::create([
        'screen_id' => $screen->id,
        'busniss_id' => $business->id,
        'video_id' => $video->id,
        'date' => now()->toDateString(),
        'seconds' => 300,
    ]);

    $this->actingAs($intruder);
    $this->get(route('business.analytics', $business))->assertForbidden();

    $this->actingAs($user);
    $response = $this->get(route('business.analytics', $business));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('todaySeconds', 300)
        ->where('totalSeconds', 300)
        ->has('daily', 1)
        ->has('perScreen', 1)
        ->has('perVideo', 1)
    );
});
