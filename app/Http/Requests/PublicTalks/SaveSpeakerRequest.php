<?php

namespace App\Http\Requests\PublicTalks;

use App\Enums\SpeakerRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSpeakerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization runs in the controller (policy + acervo ownership).
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
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
            'role' => ['required', Rule::enum(SpeakerRole::class)],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'outline_ids' => ['array'],
            'outline_ids.*' => ['string', Rule::exists('public_talk_outlines', 'id')],
        ];
    }

    /**
     * The validated attributes that map onto the Speaker model.
     *
     * @return array<string, mixed>
     */
    public function speakerAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'role' => $this->validated('role'),
            'phone' => $this->validated('phone'),
            'is_active' => $this->boolean('is_active', true),
            'notes' => $this->validated('notes'),
        ];
    }
}
