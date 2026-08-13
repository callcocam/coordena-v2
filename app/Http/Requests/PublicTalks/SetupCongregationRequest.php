<?php

namespace App\Http\Requests\PublicTalks;

use App\Models\TalkAssignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SetupCongregationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null && Gate::allows('create', [TalkAssignment::class, $team]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $ownerId = $this->user()?->currentTeam?->owner()?->id;

        return [
            'congregation_id' => [
                'nullable',
                'string',
                Rule::exists('congregations', 'id')
                    ->where('owner_user_id', $ownerId)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required_without:congregation_id', 'nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'meeting_weekday' => ['nullable', 'integer', 'between:0,6'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
        ];
    }
}
