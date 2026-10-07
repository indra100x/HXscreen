<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->logAuthAttempts();
        $this->expireSessionsOnServerBoot();
    }

    /**
     * Baseline abuse protection for the API: 60 requests per minute per
     * user (or per IP for token devices). Sensitive routes carry stricter
     * limits of their own.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id
                    ?: $request->input('device_token')
                    ?: $request->bearerToken()
                    ?: $request->ip()
            );
        });
    }

    /**
     * Temporary login diagnostics (no passwords logged).
     */
    protected function logAuthAttempts(): void
    {
        Event::listen(Login::class, function (Login $event) {
            Log::info('Auth login succeeded', ['email' => $event->user->email]);
        });
        Event::listen(Failed::class, function (Failed $event) {
            Log::warning('Auth login failed', [
                'email' => $event->credentials['email'] ?? null,
            ]);
        });
    }

    /**
     * Sessions live in the database so they outlive any single process;
     * wipe them whenever the dev server boots so a reboot always lands on
     * the login screen. Guarded for clones that haven't migrated yet.
     */
    protected function expireSessionsOnServerBoot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (! in_array($event->command, ['serve', 'dev'], true)) {
                return;
            }

            try {
                if (Schema::hasTable('sessions')) {
                    DB::table('sessions')->delete();
                }
            } catch (\Throwable $e) {
                // Never block the server from starting.
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
