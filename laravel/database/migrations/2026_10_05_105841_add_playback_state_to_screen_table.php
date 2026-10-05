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
        Schema::table('screen', function (Blueprint $table) {
            $table->string('current_video_id')->nullable()->after('last_seen_at');
            $table->unsignedBigInteger('current_position_ms')->nullable()->after('current_video_id');
            $table->timestamp('position_reported_at')->nullable()->after('current_position_ms');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screen', function (Blueprint $table) {
            $table->dropColumn(['current_video_id', 'current_position_ms', 'position_reported_at']);
        });
    }
};
