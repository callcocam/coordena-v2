<?php

namespace App\Http\Requests\PublicTalks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitExchangePortalRequest extends FormRequest
{
    /**
     * The portal is public; the token in the URL is the credential.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'offers' => ['required', 'array', 'min:1', 'max:10'],
            'offers.*.speaker_name' => ['required', 'string', 'max:255'],
            'offers.*.phone' => ['nullable', 'string', 'max:32'],
            'offers.*.outline_number' => ['nullable', 'integer', 'min:1'],
            'offers.*.date' => ['required', 'date'],
        ];
    }
}
