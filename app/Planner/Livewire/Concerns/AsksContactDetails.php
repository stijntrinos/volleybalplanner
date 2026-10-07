<?php

namespace App\Planner\Livewire\Concerns;

use App\Planner\Enums\ContactPreference;
use Closure;
use Illuminate\Validation\Rule;

/**
 * The mobile number and contact preference, shared by the match form and the
 * duty form. Email stays required: the confirmation goes there. Whoever picks
 * WhatsApp has to add a number.
 */
trait AsksContactDetails
{
    public string $phone = '';

    public string $contactPreference = 'email';

    /** @return array<string, list<mixed>> */
    protected function contactRules(): array
    {
        return [
            'contactPreference' => ['required', Rule::enum(ContactPreference::class)],
            'phone' => [
                Rule::requiredIf($this->contactPreference === ContactPreference::Whatsapp->value),
                'nullable',
                'string',
                'max:30',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && self::normalizePhone((string) $value) === null) {
                        $fail(__('Vul een geldig mobiel nummer in, bijvoorbeeld 06 12345678.'));
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    protected function contactMessages(): array
    {
        return [
            'contactPreference.required' => __('Kies hoe we contact met je opnemen.'),
            'contactPreference.enum' => __('Kies e-mail of WhatsApp.'),
            'phone.required' => __('Vul je mobiele nummer in als je via WhatsApp benaderd wilt worden.'),
        ];
    }

    /** @return array{phone: ?string, contact_preference: string} */
    protected function contactDetails(): array
    {
        return [
            'phone' => filled($this->phone) ? self::normalizePhone($this->phone) : null,
            'contact_preference' => $this->contactPreference,
        ];
    }

    /**
     * A Dutch mobile number like 06 12345678, or an international number with
     * country code. Spaces, dashes and parentheses are allowed.
     */
    public static function normalizePhone(string $number): ?string
    {
        $bare = preg_replace('/[\s\-().]/', '', $number) ?? '';
        $bare = preg_replace('/^00/', '+', $bare) ?? $bare;

        if (preg_match('/^(?:\+31|0)(6\d{8})$/', $bare, $parts)) {
            return '06 '.substr($parts[1], 1);
        }

        return preg_match('/^\+[1-9]\d{8,14}$/', $bare) ? $bare : null;
    }
}
