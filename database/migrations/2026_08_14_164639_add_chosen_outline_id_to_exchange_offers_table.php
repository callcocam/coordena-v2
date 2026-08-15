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
        Schema::table('exchange_offers', function (Blueprint $table) {
            $table->foreignUlid('chosen_outline_id')
                ->nullable()
                ->after('target_date')
                ->constrained('public_talk_outlines')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_offers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chosen_outline_id');
        });
    }
};
