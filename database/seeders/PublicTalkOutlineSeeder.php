<?php

namespace Database\Seeders;

use App\Enums\PublicTalkOutlineStatus;
use App\Models\PublicTalkOutline;
use Illuminate\Database\Seeder;

/**
 * Catálogo oficial dos esboços de discurso público (S-99a).
 *
 * Idempotente por `number`: rodar de novo só cria o que falta e completa a
 * URL de referência quando ausente — títulos ajustados manualmente são
 * preservados.
 */
class PublicTalkOutlineSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('data/public_talk_outlines.php');

        foreach ($data['outlines'] as $number => $outline) {
            $existing = PublicTalkOutline::query()->where('number', $number)->first();

            if ($existing === null) {
                PublicTalkOutline::query()->create([
                    'number' => $number,
                    'title' => $outline['title'],
                    'reference_url' => $outline['url'] ?? null,
                    'status' => PublicTalkOutlineStatus::Active,
                ]);

                continue;
            }

            if ($existing->reference_url === null && ! empty($outline['url'])) {
                $existing->update(['reference_url' => $outline['url']]);
            }
        }
    }
}
