<?php

namespace App\Http\Requests\PublicTalks;

use App\Enums\TalkAssignmentType;
use App\Models\TalkAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleSlotRequest extends FormRequest
{
    /**
     * Only home slots of the current team can be edited; incoming/outgoing
     * slots stay read-only until fase 2.
     */
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');
        $team = $this->user()?->currentTeam;

        return $assignment instanceof TalkAssignment
            && $team !== null
            && $assignment->team_id === $team->id
            && $assignment->type === TalkAssignmentType::Home
            && $this->user()->can('update', $assignment);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $homeCongregationId = $this->user()?->currentTeam?->home_congregation_id;

        return [
            'speaker_id' => [
                'nullable',
                'string',
                Rule::exists('speakers', 'id')
                    ->where('congregation_id', $homeCongregationId)
                    ->whereNull('deleted_at'),
            ],
            'outline_id' => ['nullable', 'string', Rule::exists('public_talk_outlines', 'id')],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'speaker_id.exists' => __('O orador precisa pertencer ao acervo da congregação da casa.'),
        ];
    }
}
