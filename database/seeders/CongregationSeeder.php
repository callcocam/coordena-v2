<?php

namespace Database\Seeders;

use App\Models\Congregation;
use App\Models\User;
use App\Support\Phone;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

/**
 * Semeia o acervo de congregações para um usuário dono.
 *
 * Usa o primeiro usuário como dono por padrão. Idempotente por
 * (dono, nome) — a mesma chave única do banco. O arquivo de dados é local e não versionado —
 * quando ausente, o seeder apenas avisa e segue.
 */
class CongregationSeeder extends Seeder
{
    public function run(?User $owner = null): void
    {
        $owner ??= User::query()->oldest()->first();

        if ($owner === null) {
            $this->command?->warn('CongregationSeeder: nenhum usuário encontrado — pulando.');

            return;
        }

        $path = database_path('data/congregations.local.php');

        if (! file_exists($path)) {
            $this->command?->warn('CongregationSeeder: database/data/congregations.local.php não encontrado — pulando.');

            return;
        }

        $rows = require $path;

        foreach ($rows as $row) {
            Congregation::query()->updateOrCreate([
                'owner_user_id' => $owner->id,
                'name' => $row['name'],
            ], [
                'city' => $row['city'] ?? null,
                'circuit' => $row['circuit'] ?? null,
                'address' => $row['address'] ?? null,
                'contact_name' => $row['contact_name'] ?? null,
                'contact_phone' => Phone::normalize($row['contact_phone'] ?? null),
                'contact_email' => $row['email'] ?? null,
                'secretary_name' => $row['secretary_name'] ?? null,
                'secretary_phone' => Phone::normalize($row['secretary_phone'] ?? null),
                'secretary_email' => $row['secretary_email'] ?? null,
                'meeting_weekday' => $row['meeting_weekday'] ?? Arr::random([Carbon::SATURDAY, Carbon::SUNDAY]),
                'meeting_time' => $row['meeting_time'] ?? Arr::random(['18:00', '18:30', '19:00', '19:30', '20:00']),
            ]);
        }
    }
}
