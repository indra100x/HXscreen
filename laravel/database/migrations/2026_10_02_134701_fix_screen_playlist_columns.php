<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // The original table used integer columns pointing at tables that do
        // not exist, while screen/playlist use UUID primary keys. Rebuild the
        // table with matching UUID foreign keys and carry over existing rows.
        Schema::create('screen_playlist_fixed', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('screen_id')->constrained('screen')->cascadeOnDelete();
            $table->foreignUuid('playlist_id')->constrained('playlist')->cascadeOnDelete();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();
        });

        $rows = DB::table('screen_playlist')->get();

        foreach ($rows as $row) {
            DB::table('screen_playlist_fixed')->insert((array) $row);
        }

        Schema::drop('screen_playlist');
        Schema::rename('screen_playlist_fixed', 'screen_playlist');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::drop('screen_playlist');

        Schema::create('screen_playlist', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('screen_id')->constrained()->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->timestamps();
        });
    }
};
