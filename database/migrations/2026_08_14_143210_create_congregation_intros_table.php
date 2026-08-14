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
        Schema::create('congregation_intros', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUlid('congregation_id')->index()->constrained()->cascadeOnDelete();
            $table->string('channel')->default('whatsapp');
            $table->string('portal_token')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->string('wamid')->nullable()->unique();
            $table->string('reactivation_wamid')->nullable()->unique();
            $table->timestamp('reactivation_prompted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('reactivated_at')->nullable();
            $table->foreignUlid('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('congregation_intro_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('intro_id')->index()->constrained('congregation_intros')->cascadeOnDelete();
            $table->string('direction');
            $table->string('channel')->default('whatsapp');
            $table->text('body');
            $table->string('wamid')->nullable()->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('congregation_intro_messages');
        Schema::dropIfExists('congregation_intros');
    }
};
