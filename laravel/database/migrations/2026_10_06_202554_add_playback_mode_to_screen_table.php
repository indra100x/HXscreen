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
            $table->string('playback_mode', 10)->default('loop')->after('pending_command');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screen', function (Blueprint $table) {
            $table->dropColumn('playback_mode');
        });
    }
};
