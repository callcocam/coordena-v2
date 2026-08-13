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
        Schema::create('exchange_invites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->index()->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->string('status')->default('open')->index();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'month']);
        });

        Schema::create('exchange_invite_sends', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invite_id')->index()->constrained('exchange_invites')->cascadeOnDelete();
            $table->foreignUlid('congregation_id')->constrained()->cascadeOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('portal_token')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->foreignUlid('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exchange_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invite_send_id')->index()->constrained('exchange_invite_sends')->cascadeOnDelete();
            $table->string('direction');
            $table->string('channel')->default('whatsapp');
            $table->text('body');
            $table->string('wamid')->nullable()->index();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('exchange_offers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invite_send_id')->index()->constrained('exchange_invite_sends')->cascadeOnDelete();
            $table->string('direction')->default('incoming');
            $table->foreignUlid('speaker_id')->constrained()->cascadeOnDelete();
            $table->date('target_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->foreignUlid('source_message_id')->nullable()->constrained('exchange_messages')->nullOnDelete();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('exchange_offer_outlines', function (Blueprint $table) {
            $table->foreignUlid('offer_id')->constrained('exchange_offers')->cascadeOnDelete();
            $table->foreignUlid('outline_id')->constrained('public_talk_outlines')->cascadeOnDelete();

            $table->primary(['offer_id', 'outline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_offer_outlines');
        Schema::dropIfExists('exchange_offers');
        Schema::dropIfExists('exchange_messages');
        Schema::dropIfExists('exchange_invite_sends');
        Schema::dropIfExists('exchange_invites');
    }
};
