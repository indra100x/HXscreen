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
        // Team membership: invited users share full management access to
        // one business. Ownership (user_id on busniss) stays separate and
        // alone grants member management and business deletion.
        Schema::create('business_user', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('busniss_id')->constrained('busniss')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['busniss_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_user');
    }
};
