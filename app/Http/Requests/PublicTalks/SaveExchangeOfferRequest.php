<?php

namespace App\Http\Requests\PublicTalks;

use App\Models\ExchangeInviteSend;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExchangeOfferRequest extends FormRequest
{
    /**
     * Offers are managed by whoever can update the invite.
     */
    public function authorize(): bool
    {
        $send = $this->route('send');

        return $send instanceof ExchangeInviteSend
            && $this->user()?->can('update', $send->invite) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ExchangeInviteSend $send */
        $send = $this->route('send');

        $speakerCongregationId = $this->input('direction') === 'outgoing'
            ? $this->user()?->currentTeam?->home_congregation_id
            : $send->congregation_id;

        return [
            'direction' => ['required', Rule::in(['incoming', 'outgoing'])],
            'speaker_id' => [
                'required',
                'string',
                Rule::exists('speakers', 'id')
                    ->where('congregation_id', $speakerCongregationId)
                    ->whereNull('deleted_at'),
            ],
            'target_date' => ['nullable', 'date'],
            'outline_ids' => ['array'],
            'outline_ids.*' => ['string', Rule::exists('public_talk_outlines', 'id')],
            'source_message_id' => [
                'nullable',
                'string',
                Rule::exists('exchange_messages', 'id')->where('invite_send_id', $send->id),
            ],
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
            'speaker_id.exists' => __('O orador precisa pertencer à congregação correta para esta direção.'),
        ];
    }
}
