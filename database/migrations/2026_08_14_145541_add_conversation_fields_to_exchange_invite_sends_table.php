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
        Schema::table('exchange_invite_sends', function (Blueprint $table) {
            $table->string('kind')->default('exchange')->after('channel');
            $table->timestamp('accepted_at')->nullable()->after('answered_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_invite_sends', function (Blueprint $table) {
            $table->dropColumn(['kind', 'accepted_at']);
        });
    }
};
