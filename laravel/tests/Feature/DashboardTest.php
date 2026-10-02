<?php

use App\Models\Business;
use App\Models\User;

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
