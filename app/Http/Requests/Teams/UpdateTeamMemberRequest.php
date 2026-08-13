<?php

namespace App\Http\Requests\Teams;

use App\Models\Role;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        $assignableKeys = Role::query()
            ->assignableForTeam($team)
            ->pluck('key')
            ->all();

        return [
            'cargos' => ['required', 'array'],
            'cargos.*' => ['string', Rule::in($assignableKeys)],
        ];
    }
}
