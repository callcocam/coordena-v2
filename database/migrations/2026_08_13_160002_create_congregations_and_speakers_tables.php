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
        Schema::create('congregations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('city')->nullable();
            $table->string('circuit')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('secretary_name')->nullable();
            $table->string('secretary_phone')->nullable();
            $table->string('secretary_email')->nullable();
            $table->unsignedTinyInteger('meeting_weekday')->nullable();
            $table->time('meeting_time')->nullable();
            $table->string('exchange_opt')->default('unknown');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['owner_user_id', 'name']);
        });

        Schema::create('speakers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('congregation_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->default('other');
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('speaker_outlines', function (Blueprint $table) {
            $table->foreignUlid('speaker_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('outline_id')->constrained('public_talk_outlines')->cascadeOnDelete();

            $table->primary(['speaker_id', 'outline_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speaker_outlines');
        Schema::dropIfExists('speakers');
        Schema::dropIfExists('congregations');
    }
};
