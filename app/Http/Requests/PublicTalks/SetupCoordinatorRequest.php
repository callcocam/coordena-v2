<?php

namespace App\Http\Requests\PublicTalks;

use App\Models\Coordinator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SetupCoordinatorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->user()?->currentTeam;

        return $team !== null && Gate::allows('create', [Coordinator::class, $team]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'helpers' => ['array'],
            'helpers.*.name' => ['required', 'string', 'max:255'],
            'helpers.*.phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
