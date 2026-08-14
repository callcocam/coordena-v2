<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A unidade de programação passa a ser a SEMANA (segunda-feira como
     * início, ISO). Backfill + dedupe de slots home|incoming da mesma
     * semana, e unique parcial garantindo 1 slot da casa por semana.
     */
    public function up(): void
    {
        Schema::table('talk_assignments', function (Blueprint $table) {
            $table->date('week_start')->nullable()->after('date')->index();
        });

        $this->backfillWeekStart();
        $this->dedupeHomeWeeks();

        Schema::table('talk_assignments', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'date', 'type']);
            $table->index(['team_id', 'date']);
        });

        DB::statement(
            "create unique index talk_assignments_team_week_home_unique on talk_assignments (team_id, week_start) where type in ('home', 'incoming')"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('drop index talk_assignments_team_week_home_unique');

        Schema::table('talk_assignments', function (Blueprint $table) {
            $table->dropIndex(['team_id', 'date']);
            $table->unique(['team_id', 'date', 'type']);
            $table->dropColumn('week_start');
        });
    }

    /**
     * `week_start` = a data retrocedida até a segunda-feira (ISO).
     */
    protected function backfillWeekStart(): void
    {
        DB::table('talk_assignments')
            ->whereNull('week_start')
            ->orderBy('id')
            ->chunkById(200, function ($assignments): void {
                foreach ($assignments as $assignment) {
                    DB::table('talk_assignments')
                        ->where('id', $assignment->id)
                        ->update([
                            'week_start' => Carbon::parse($assignment->date)
                                ->startOfWeek(Carbon::MONDAY)
                                ->toDateString(),
                        ]);
                }
            });
    }

    /**
     * Para semanas com mais de um slot home|incoming do mesmo time, mantém o
     * mais "avançado" (com orador/status != open; empate: o mais recente) e
     * apaga os demais.
     */
    protected function dedupeHomeWeeks(): void
    {
        $duplicated = DB::table('talk_assignments')
            ->selectRaw('team_id, week_start')
            ->whereIn('type', ['home', 'incoming'])
            ->groupBy('team_id', 'week_start')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicated as $group) {
            $slots = DB::table('talk_assignments')
                ->where('team_id', $group->team_id)
                ->where('week_start', $group->week_start)
                ->whereIn('type', ['home', 'incoming'])
                ->get()
                ->sortByDesc(fn (object $slot): array => [
                    $slot->status !== 'open' ? 1 : 0,
                    $slot->speaker_id !== null ? 1 : 0,
                    (string) $slot->updated_at,
                    $slot->id,
                ]);

            DB::table('talk_assignments')
                ->whereIn('id', $slots->skip(1)->pluck('id')->all())
                ->delete();
        }
    }
};
