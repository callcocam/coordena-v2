<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail proving a user accepted the WhatsApp terms of use for a team:
 * who, when, from which IP / user agent, and which terms version. A team is
 * "activated" for API sends once any manager has a row for the current version.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_terms_acceptances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('version');
            $table->string('ip_address')->nullable();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->index(['team_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_terms_acceptances');
    }
};
