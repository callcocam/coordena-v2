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
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUlid('home_congregation_id')
                ->nullable()
                ->constrained('congregations')
                ->nullOnDelete();
        });

        Schema::create('coordinators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->index()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('role')->default('helper');
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('talk_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('team_id')->index()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('type');
            $table->foreignUlid('speaker_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('outline_id')->nullable()->constrained('public_talk_outlines')->nullOnDelete();
            $table->foreignUlid('counterpart_congregation_id')->nullable()->constrained('congregations')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->foreignUlid('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'date', 'type']);
        });

        Schema::create('talk_assignment_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('talk_assignment_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignUlid('speaker_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('assignment');
            $table->string('wamid')->nullable()->unique();
            $table->string('status')->default('pending')->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->foreignUlid('sent_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('talk_assignment_notifications');
        Schema::dropIfExists('talk_assignments');
        Schema::dropIfExists('coordinators');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('home_congregation_id');
        });
    }
};
