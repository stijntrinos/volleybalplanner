{{-- Name, email, contact preference and the submit button; shared by the match and the duty form. --}}
<div class="grid gap-5 sm:grid-cols-2">
    <div>
        <label for="{{ $prefix }}-name" class="{{ $label }}">{{ __('Je naam') }}</label>
        <input id="{{ $prefix }}-name" type="text" wire:model="name" maxlength="120" autocomplete="name" class="{{ $field }}">
        @error('name')<span class="{{ $error }}">{{ $message }}</span>@enderror
    </div>
    <div>
        <label for="{{ $prefix }}-email" class="{{ $label }}">{{ __('Je e-mailadres') }}</label>
        <input id="{{ $prefix }}-email" type="email" wire:model="email" maxlength="191" autocomplete="email" class="{{ $field }}">
        @error('email')<span class="{{ $error }}">{{ $message }}</span>@enderror
    </div>
</div>

@include('planner::livewire.partials.contact-preference', ['prefix' => $prefix])

<div class="border-t border-gray-200 pt-6">
    <button type="submit" wire:loading.attr="disabled" class="planner-button w-full sm:w-auto">
        <span wire:loading.remove wire:target="submit">{{ __('Verzoek versturen') }}</span>
        <span wire:loading wire:target="submit">{{ __('Versturen…') }}</span>
    </button>
</div>
