<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A team's official WhatsApp number credentials (Meta Cloud API). The number is
 * registered on the Meta side, so there is no QR/connect step — filling these
 * columns is the whole connection. One row per team.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_whatsapp_connections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('waba_id')->nullable();            // WhatsApp Business Account id
            $table->string('phone_number_id')->nullable();    // resolves the team from a webhook payload
            $table->text('cloud_access_token')->nullable();   // System User permanent token (encrypted in the model)
            $table->string('app_id')->nullable();             // Meta app the number is registered under
            $table->string('verified_name')->nullable();      // display name approved by Meta
            $table->string('quality_rating')->nullable();     // GREEN/YELLOW/RED, synced from Meta
            $table->string('messaging_limit')->nullable();    // current tier, e.g. TIER_1K
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_whatsapp_connections');
    }
};
