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
            $table->string('pairing_code')->nullable()->after('device_id');
            $table->string('device_token')->nullable()->after('pairing_code');
            $table->timestamp('pairing_code_expires_at')->nullable()->after('device_token');
            $table->timestamp('device_token_expires_at')->nullable()->after('pairing_code_expires_at');
            $table->timestamp('paired_at')->nullable()->after('device_token_expires_at');
            $table->timestamp('last_seen_at')->nullable()->after('paired_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screen', function (Blueprint $table) {
            $table->dropColumn([
                'pairing_code',
                'device_token',
                'pairing_code_expires_at',
                'device_token_expires_at',
                'paired_at',
                'last_seen_at',
            ]);
        });
    }
};
