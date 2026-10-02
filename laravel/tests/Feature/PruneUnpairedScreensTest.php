<?php

use App\Jobs\PruneUnpairedScreens;
use App\Models\Business;
use App\Models\Screen;
use App\Models\User;

it('prunes only stale unpaired screens', function () {
    $user = User::factory()->create();
    $business = Business::create(['user_id' => $user->id, 'name' => 'Acme']);

    $stale = Screen::create(['name' => 'Stale', 'busniss_id' => null, 'device_id' => 'stale']);
    $stale->created_at = now()->subDays(2);
    $stale->save();

    $fresh = Screen::create(['name' => 'Fresh', 'busniss_id' => null, 'device_id' => 'fresh']);

    $paired = Screen::create(['name' => 'Paired', 'busniss_id' => $business->id, 'device_id' => 'paired']);
    $paired->created_at = now()->subDays(2);
    $paired->save();

    (new PruneUnpairedScreens)->handle();

    expect(Screen::find($stale->id))->toBeNull()
        ->and(Screen::find($fresh->id))->not->toBeNull()
        ->and(Screen::find($paired->id))->not->toBeNull();
});
