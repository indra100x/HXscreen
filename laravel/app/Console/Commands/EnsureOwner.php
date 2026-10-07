<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:ensure-owner')]
#[Description('Create or update the install-time owner account and its venue from env (OWNER_EMAIL, OWNER_PASSWORD, OWNER_NAME, BUSINESS_NAME). Idempotent.')]
class EnsureOwner extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = trim((string) (config('app.owner_email') ?? ''));
        $password = (string) (config('app.owner_password') ?? '');
        $name = trim((string) (config('app.owner_name') ?? '')) ?: 'Owner';
        $businessName = trim((string) (config('app.owner_business') ?? '')) ?: (string) config('app.name', 'HXscreen');

        if ($email === '' || $password === '') {
            $this->error('OWNER_EMAIL and OWNER_PASSWORD must be set (via OWNER_* env vars). Nothing to do.');

            return self::FAILURE;
        }

        if (strlen($password) < 8) {
            $this->error('OWNER_PASSWORD must be at least 8 characters.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->forceFill([
                'name' => $name,
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
            $this->info("Owner updated: {$email}");
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->info("Owner created: {$email}");
        }

        $business = $user->businesses()->orderBy('name')->first();

        if (! $business) {
            $business = Business::create([
                'user_id' => $user->id,
                'name' => mb_substr($businessName, 0, 50),
            ]);
            $this->info("Venue created: {$business->name}");
        }

        $this->info("Ready: {$email} owns \"{$business->name}\" ({$business->id})");

        return self::SUCCESS;
    }
}
