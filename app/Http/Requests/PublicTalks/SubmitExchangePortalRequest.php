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
     * `incoming` = speakers the invited congregation sends to our open weeks —
     * either a registered partner speaker (`speaker_id`) or a new name typed
     * in the combobox (`speaker_name`, silent firstOrCreate on submit);
     * `outgoing` = weeks where they receive one of our speakers, with the
     * outline THEY chose (regra do tema: quem recebe escolhe).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'incoming' => ['required_without:outgoing', 'array', 'max:10'],
            'incoming.*.week' => ['required', 'date'],
            'incoming.*.speaker_id' => ['nullable', 'string', 'max:36'],
            'incoming.*.speaker_name' => ['required_without:incoming.*.speaker_id', 'nullable', 'string', 'max:255'],
            'incoming.*.phone' => ['nullable', 'string', 'max:32'],
            'incoming.*.outline_ids' => ['nullable', 'array', 'max:20'],
            'incoming.*.outline_ids.*' => ['string', 'max:36'],
            'outgoing' => ['required_without:incoming', 'array', 'max:10'],
            'outgoing.*.week' => ['required', 'date'],
            'outgoing.*.speaker_id' => ['required', 'string', 'max:36'],
            'outgoing.*.outline_id' => ['required', 'string', 'max:36'],
        ];
    }
}
