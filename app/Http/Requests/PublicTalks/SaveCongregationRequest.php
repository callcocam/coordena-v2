<?php

namespace App\Http\Requests\PublicTalks;

use App\Enums\ExchangeOpt;
use App\Models\Congregation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveCongregationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $congregation = $this->route('congregation');

        return $congregation instanceof Congregation
            ? Gate::allows('update', $congregation)
            : Gate::allows('create', Congregation::class);
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
            'city' => ['nullable', 'string', 'max:120'],
            'circuit' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'secretary_name' => ['nullable', 'string', 'max:255'],
            'secretary_phone' => ['nullable', 'string', 'max:32'],
            'secretary_email' => ['nullable', 'email', 'max:255'],
            'meeting_weekday' => ['nullable', 'integer', 'between:0,6'],
            'meeting_time' => ['nullable', 'date_format:H:i'],
            'exchange_opt' => ['nullable', Rule::enum(ExchangeOpt::class)],
        ];
    }
}
