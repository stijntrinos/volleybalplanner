<?php

namespace App\Http\Requests;

use App\Enums\ContactTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public const TEAMS = ['Tot 15', '15 tot 30', 'Meer dan 30'];

    public const VENUES = ['1', '2 of 3', '4 of meer'];

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topic' => ['required', Rule::enum(ContactTopic::class)->only(ContactTopic::publicCases())],
            'name' => ['required', 'string', 'max:255'],
            'club' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'teams' => ['nullable', Rule::in(self::TEAMS)],
            'venues' => ['nullable', Rule::in(self::VENUES)],
            'message' => ['nullable', 'string', 'max:5000'],
            'privacy' => ['accepted'],
            // Honeypot: people never see this field, bots fill it in.
            'website' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'name' => 'naam',
            'club' => 'vereniging',
            'email' => 'e-mailadres',
            'phone' => 'telefoon',
            'message' => 'bericht',
            'privacy' => 'privacyverklaring',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'privacy.accepted' => 'Ga akkoord met de privacyverklaring om het formulier te versturen.',
        ];
    }
}
