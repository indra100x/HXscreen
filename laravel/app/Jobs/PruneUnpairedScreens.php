<?php

namespace App\Jobs;

use App\Models\Screen;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PruneUnpairedScreens implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Rows are recreated on the next request-pairing-code call, and any
        // live pairing code expires after 10 minutes, so dropping stale
        // unpaired rows is safe.
        $deleted = Screen::whereNull('busniss_id')
            ->where('created_at', '<', now()->subDay())
            ->delete();

        Log::info('PruneUnpairedScreens completed', ['deleted' => $deleted]);
    }
}
