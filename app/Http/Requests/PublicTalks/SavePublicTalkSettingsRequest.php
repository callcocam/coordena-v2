<?php

namespace App\Http\Requests\PublicTalks;

use App\Models\TalkAssignment;
use App\Services\PublicTalks\PublicTalkSettings;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Overrides por time dos prazos do módulo de discursos. Todo campo é
 * nullable: vazio significa "voltar ao padrão global". As regras cruzadas
 * comparam os valores efetivos (informado ou, se vazio, o default), para o
 * par reengate/expiração e os dois lembretes nunca ficarem incoerentes.
 */
class SavePublicTalkSettingsRequest extends FormRequest
{
    /**
     * Only whoever manages the schedule can change the module settings.
     */
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null
            && $this->user()->can('create', [TalkAssignment::class, $team]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'speaker_reminder_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'speaker_second_reminder_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'pending_alert_days' => ['nullable', 'integer', 'min:0', 'max:30'],
            'exchange_nudge_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'exchange_expire_days' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    /**
     * Cross-field checks over the effective values.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->effective('speaker_second_reminder_days') >= $this->effective('speaker_reminder_days')) {
                $validator->errors()->add(
                    'speaker_second_reminder_days',
                    __('O repique precisa ser mais perto do discurso que o primeiro lembrete.'),
                );
            }

            if ($this->effective('exchange_expire_days') <= $this->effective('exchange_nudge_days')) {
                $validator->errors()->add(
                    'exchange_expire_days',
                    __('A expiração precisa vir depois do reengate.'),
                );
            }
        });
    }

    /**
     * The overrides to persist: every known key, null when left empty.
     *
     * @return array<string, int|null>
     */
    public function overrides(): array
    {
        return collect(array_keys(PublicTalkSettings::CONFIG_KEYS))
            ->mapWithKeys(fn (string $key): array => [
                $key => $this->filled($key) ? $this->integer($key) : null,
            ])
            ->all();
    }

    /**
     * The value a key will effectively have after this save.
     */
    protected function effective(string $key): int
    {
        return $this->filled($key)
            ? $this->integer($key)
            : PublicTalkSettings::default($key);
    }
}
