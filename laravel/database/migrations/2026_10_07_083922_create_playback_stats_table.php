<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Daily play-time aggregates, fed by heartbeats: one row per
        // screen + video + day. Seconds accumulate from the time elapsed
        // between consecutive playing heartbeats (capped per beat).
        Schema::create('playback_stats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('screen_id')->constrained('screen')->cascadeOnDelete();
            $table->foreignUuid('busniss_id')->nullable()->constrained('busniss')->nullOnDelete();
            $table->foreignUuid('video_id')->nullable()->constrained('video')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamps();

            $table->unique(['screen_id', 'video_id', 'date'], 'playback_screen_video_day');
            $table->index(['busniss_id', 'date'], 'playback_business_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_stats');
    }
};
