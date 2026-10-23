@php use App\Planner\Enums\ContactPreference; @endphp

{{-- Shared by the match and the duty form. $prefix keeps the ids unique. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="{{ $prefix }}-phone" class="{{ $label }}">
            {{ __('Je mobiele nummer') }}
            @if ($contactPreference !== ContactPreference::Whatsapp->value)
                <span class="font-normal text-gray-500">({{ __('optioneel') }})</span>
            @endif
        </label>
        <input id="{{ $prefix }}-phone" type="tel" wire:model="phone" maxlength="30" autocomplete="tel" inputmode="tel" placeholder="06 12345678" class="{{ $field }}">
        @error('phone')<span class="{{ $error }}">{{ $message }}</span>@enderror
    </div>
    <fieldset>
        <legend class="{{ $label }}">{{ __('Hoe nemen we contact met je op?') }}</legend>
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach (ContactPreference::cases() as $option)
                <label class="planner-option flex cursor-pointer items-center gap-2 border px-4 py-2.5 {{ $contactPreference === $option->value ? 'planner-border-primary planner-tint-primary' : 'border-gray-300 bg-white hover:border-gray-400' }}">
                    <input type="radio" name="{{ $prefix }}-contact-preference" value="{{ $option->value }}" wire:model.live="contactPreference">
                    <span class="text-gray-900">{{ $option->getLabel() }}</span>
                </label>
            @endforeach
        </div>
        @error('contactPreference')<span class="{{ $error }}">{{ $message }}</span>@enderror
    </fieldset>
</div>
