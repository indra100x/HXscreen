<?php

use App\Models\Business;
use App\Models\User;

test('public registration is disabled', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
    $this->assertGuest();
});

test('ensure-owner seeds the install-time login and venue', function () {
    config()->set('app.owner_name', 'Shop Owner');
    config()->set('app.owner_email', 'owner@example.com');
    config()->set('app.owner_password', 'install-secret-1');
    config()->set('app.owner_business', 'Corner Shop');

    $this->artisan('app:ensure-owner')->assertSuccessful();

    $user = User::where('email', 'owner@example.com')->firstOrFail();
    expect($user->email_verified_at)->not->toBeNull();

    $business = $user->businesses()->firstOrFail();
    expect($business->name)->toBe('Corner Shop');

    // Idempotent: second run updates password, creates nothing.
    config()->set('app.owner_password', 'install-secret-2');
    $this->artisan('app:ensure-owner')->assertSuccessful();

    expect($user->fresh()->businesses()->count())->toBe(1);
    expect(Business::count())->toBe(1);
    $this->assertTrue(Hash::check('install-secret-2', $user->fresh()->password));
});

test('ensure-owner refuses weak or missing credentials', function () {
    config()->set('app.owner_email', null);
    config()->set('app.owner_password', null);
    $this->artisan('app:ensure-owner')->assertFailed();

    config()->set('app.owner_email', 'owner@example.com');
    config()->set('app.owner_password', 'short');
    $this->artisan('app:ensure-owner')->assertFailed();
    expect(User::where('email', 'owner@example.com')->exists())->toBeFalse();
});

test('an account cannot create a second venue', function () {
    $user = User::factory()->create();
    Business::create(['user_id' => $user->id, 'name' => 'First']);
    $this->actingAs($user);

    $this->postJson('/api/businesses', ['name' => 'Second'])->assertUnprocessable();
    expect($user->businesses()->count())->toBe(1);
});
