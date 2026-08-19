<?php

namespace Database\Seeders;

use App\Enums\CoordinatorRole;
use App\Enums\DefaultCargo;
use App\Enums\SpeakerRole;
use App\Models\Congregation;
use App\Models\PublicTalkOutline;
use App\Models\Speaker;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dados de demonstração do módulo de discursos: oradores por congregação,
 * esboços por orador e um time com coordenador responsável.
 *
 * Determinístico e idempotente: rodar 2× não duplica nada.
 */
class PublicTalkDemoSeeder extends Seeder
{
    protected const DEMO_TEAM_NAME = 'Arranjo de Oradores (Demo)';

    protected const CONGREGATIONS = 12;

    /** @var list<int> */
    protected const SPEAKERS_PER_CONGREGATION = [5, 3, 7, 4, 6];

    /** @var list<int> */
    protected const OUTLINES_PER_SPEAKER = [6, 4, 9, 5, 7, 3, 8];

    /** @var list<string> */
    protected const FIRST_NAMES = [
        'Carlos', 'João', 'Marcos', 'Paulo', 'Pedro', 'Lucas', 'Daniel',
        'Rafael', 'Gustavo', 'André', 'Felipe', 'Tiago', 'Samuel', 'Davi',
    ];

    /** @var list<string> */
    protected const LAST_NAMES = [
        'Silva', 'Santos', 'Oliveira', 'Souza', 'Pereira', 'Costa',
        'Almeida', 'Nascimento', 'Lima', 'Araújo', 'Ribeiro', 'Monteiro',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $owner = User::query()->oldest()->first();

        if ($owner === null) {
            $this->command?->warn('PublicTalkDemoSeeder: nenhum usuário encontrado — pulando.');

            return;
        }

        $congregations = Congregation::query()
            ->where('owner_user_id', $owner->id)
            ->orderBy('name')
            ->limit(self::CONGREGATIONS)
            ->get();

        if ($congregations->isEmpty()) {
            $this->command?->warn('PublicTalkDemoSeeder: nenhuma congregação no acervo — pulando.');

            return;
        }

        $outlineIds = PublicTalkOutline::query()
            ->orderBy('number')
            ->pluck('id')
            ->all();

        foreach ($congregations as $index => $congregation) {
            $this->seedSpeakers($congregation, $index, $outlineIds);
        }

        $this->seedTeam($owner, $congregations->first());
    }

    /**
     * @param  list<string>  $outlineIds
     */
    protected function seedSpeakers(Congregation $congregation, int $index, array $outlineIds): void
    {
        $count = self::SPEAKERS_PER_CONGREGATION[$index % count(self::SPEAKERS_PER_CONGREGATION)];

        for ($i = 0; $i < $count; $i++) {
            $name = sprintf(
                '%s %s',
                self::FIRST_NAMES[($index * 5 + $i) % count(self::FIRST_NAMES)],
                self::LAST_NAMES[($index * 3 + $i * 2) % count(self::LAST_NAMES)],
            );

            $speaker = Speaker::query()->firstOrCreate([
                'congregation_id' => $congregation->id,
                'name' => $name,
            ], [
                'role' => $i % 3 === 0 ? SpeakerRole::MinisterialServant : SpeakerRole::Elder,
                'is_active' => true,
                'phone' => $this->demoPhone($index, $i),
            ]);

            // Backfill para bases semeadas antes do telefone existir no seeder:
            // sem telefone os envios de WhatsApp do demo falham.
            if ($speaker->phone === null) {
                $speaker->update(['phone' => $this->demoPhone($index, $i)]);
            }

            if ($outlineIds !== []) {
                $speaker->outlines()->syncWithoutDetaching(
                    $this->outlinesFor($index, $i, $outlineIds),
                );
            }
        }
    }

    /**
     * Deterministic fake WhatsApp phone for a demo speaker (DDI 55 + DDD 51).
     */
    protected function demoPhone(int $congregationIndex, int $speakerIndex): string
    {
        return sprintf('5551998%02d%03d0', $congregationIndex % 100, $speakerIndex % 1000);
    }

    /**
     * Deterministic outline selection for a speaker.
     *
     * @param  list<string>  $outlineIds
     * @return list<string>
     */
    protected function outlinesFor(int $congregationIndex, int $speakerIndex, array $outlineIds): array
    {
        $count = self::OUTLINES_PER_SPEAKER[($congregationIndex + $speakerIndex) % count(self::OUTLINES_PER_SPEAKER)];
        $total = count($outlineIds);
        $ids = [];

        for ($j = 0; $j < $count; $j++) {
            $ids[] = $outlineIds[($congregationIndex * 7 + $speakerIndex * 13 + $j * 11) % $total];
        }

        return array_values(array_unique($ids));
    }

    protected function seedTeam(User $owner, Congregation $home): void
    {
        $team = $owner->ownedTeams()->where('name', self::DEMO_TEAM_NAME)->first();

        if ($team === null) {
            // Slug explícito: o DatabaseSeeder roda sem model events, então o
            // hook de geração de slug do Team não dispara aqui.
            $team = Team::query()->create([
                'name' => self::DEMO_TEAM_NAME,
                'slug' => Str::slug(self::DEMO_TEAM_NAME),
                'is_personal' => false,
            ]);

            $team->memberships()->create([
                'user_id' => $owner->id,
                'is_owner' => true,
            ]);

            $owner->assignCargo($team, DefaultCargo::Coordenador->value);
        }

        if ($team->home_congregation_id === null) {
            $team->update(['home_congregation_id' => $home->id]);
        }

        $team->coordinators()->firstOrCreate([
            'name' => $home->contact_name ?? 'Coordenador de Discursos',
        ], [
            'phone' => $home->contact_phone,
            'role' => CoordinatorRole::Responsible,
            'is_active' => true,
            'user_id' => $owner->id,
        ]);
    }
}
