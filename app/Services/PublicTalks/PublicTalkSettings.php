<?php

namespace App\Services\PublicTalks;

use App\Models\Team;
use InvalidArgumentException;

/**
 * Prazos do módulo de discursos com override por time.
 *
 * Cada chave tem um default global em `config/public_talks.php` (via env) e um
 * override opcional por time na coluna jsonb `teams.public_talk_settings` —
 * chave ausente = usa o default. `save()` descarta overrides iguais ao default
 * para não fossilizar o valor global no banco.
 */
class PublicTalkSettings
{
    /**
     * Chave da tela → chave em `config/public_talks.php`.
     *
     * @var array<string, string>
     */
    public const CONFIG_KEYS = [
        'speaker_reminder_days' => 'public_talks.reminders.speaker_days_before',
        'speaker_second_reminder_days' => 'public_talks.reminders.speaker_second_days_before',
        'pending_alert_days' => 'public_talks.reminders.pending_days_before',
        'exchange_nudge_days' => 'public_talks.exchange.nudge_after_days',
        'exchange_expire_days' => 'public_talks.exchange.expire_after_days',
    ];

    /**
     * Defaults do código, caso o arquivo de config suma ou venha com a chave
     * vazia. Espelham os defaults de `config/public_talks.php`.
     *
     * @var array<string, int>
     */
    protected const FALLBACKS = [
        'speaker_reminder_days' => 3,
        'speaker_second_reminder_days' => 1,
        'pending_alert_days' => 0,
        'exchange_nudge_days' => 4,
        'exchange_expire_days' => 10,
    ];

    protected ?Team $team = null;

    /**
     * Bind the resolver to a team (returns a fresh instance, no shared state).
     */
    public function for(Team $team): static
    {
        $bound = clone $this;
        $bound->team = $team;

        return $bound;
    }

    /**
     * Effective value of a key: the team's override, or the global default.
     */
    public function get(string $key): int
    {
        $this->assertKnown($key);

        $override = $this->overrides()[$key] ?? null;

        return $override ?? static::default($key);
    }

    /**
     * All effective values at once — what the screen shows and the commands use.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        return collect(array_keys(static::CONFIG_KEYS))
            ->mapWithKeys(fn (string $key): array => [$key => $this->get($key)])
            ->all();
    }

    /**
     * Only the keys the team customized.
     *
     * @return array<string, int>
     */
    public function overrides(): array
    {
        $stored = $this->team()->public_talk_settings ?? [];

        return collect($stored)
            ->only(array_keys(static::CONFIG_KEYS))
            ->filter(fn (mixed $value): bool => is_numeric($value))
            ->map(fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * Persist the overrides: null/missing key or a value equal to the global
     * default clears the override; an empty set stores NULL in the column.
     *
     * @param  array<string, int|null>  $overrides
     */
    public function save(array $overrides): void
    {
        collect(array_keys($overrides))->each(fn (string $key) => $this->assertKnown($key));

        $kept = collect($overrides)
            ->filter(fn (mixed $value): bool => $value !== null)
            ->map(fn (mixed $value): int => (int) $value)
            ->reject(fn (int $value, string $key): bool => $value === static::default($key))
            ->all();

        $this->team()->forceFill([
            'public_talk_settings' => $kept === [] ? null : $kept,
        ])->save();
    }

    /**
     * The global default of a key (config, then the code fallback).
     */
    public static function default(string $key): int
    {
        static::assertKnown($key);

        $value = config(static::CONFIG_KEYS[$key]);

        return is_numeric($value) ? (int) $value : static::FALLBACKS[$key];
    }

    /**
     * All global defaults at once.
     *
     * @return array<string, int>
     */
    public static function defaults(): array
    {
        return collect(array_keys(static::CONFIG_KEYS))
            ->mapWithKeys(fn (string $key): array => [$key => static::default($key)])
            ->all();
    }

    /**
     * Fail loudly on a typo'd key instead of silently falling back.
     */
    protected static function assertKnown(string $key): void
    {
        if (! array_key_exists($key, static::CONFIG_KEYS)) {
            throw new InvalidArgumentException("Unknown public talk setting [{$key}].");
        }
    }

    /**
     * The bound team, guarded against usage without `for()`.
     */
    protected function team(): Team
    {
        if ($this->team === null) {
            throw new InvalidArgumentException('Call for($team) before reading or saving settings.');
        }

        return $this->team;
    }
}
