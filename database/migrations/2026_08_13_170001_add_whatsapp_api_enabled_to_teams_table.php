<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic (Meta Cloud API) × manual WhatsApp mode per team. Default `true`:
 * a team starts able to use the API once a manager accepts the terms and the
 * shared/own number is configured; flipping it off puts the team in manual mode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('whatsapp_api_enabled')->default(true)->after('is_personal');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('whatsapp_api_enabled');
        });
    }
};
