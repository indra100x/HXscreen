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
        // device_id is looked up on every pairing/claim; device_token on
        // every heartbeat, content, command and refresh call. device_id is
        // additionally unique because pairing assumes one row per device.
        Schema::table('screen', function (Blueprint $table) {
            $table->unique('device_id');
            $table->index('device_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screen', function (Blueprint $table) {
            $table->dropUnique(['device_id']);
            $table->dropIndex(['device_token']);
        });
    }
};
