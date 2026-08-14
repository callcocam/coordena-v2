<?php

namespace App\Http\Requests\PublicTalks;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExchangeSendRequest extends FormRequest
{
    /**
     * Sending invites requires the notify permission (checked in controller
     * against the invite); here we only gate on an authenticated team member.
     */
    public function authorize(): bool
    {
        return $this->user()?->currentTeam !== null;
    }

    /**
     * The manual channel remains the default so older callers that never
     * send an explicit channel keep working.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('channel')) {
            $this->merge(['channel' => 'manual']);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->user()?->currentTeam;
        $owner = $team?->owner();

        return [
            'month' => ['required', 'date_format:Y-m'],
            'channel' => ['required', Rule::in(['manual', 'whatsapp'])],
            'congregation_id' => [
                'required',
                'string',
                Rule::exists('congregations', 'id')
                    ->where('owner_user_id', $owner?->id)
                    ->whereNull('deleted_at'),
                Rule::notIn([$team?->home_congregation_id]),
            ],
        ];
    }
}
