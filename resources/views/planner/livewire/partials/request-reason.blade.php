@php use App\Planner\Enums\ChangeReason; @endphp

{{-- Reason, "other" text and explanation; shared by the match and the duty form. --}}
<div>
    <label for="{{ $prefix }}-reason" class="{{ $label }}">{{ __('Reden') }}</label>
    <select id="{{ $prefix }}-reason" wire:model.live="reason" class="{{ $field }}">
        <option value="">{{ __('- kies een reden -') }}</option>
        @foreach ($this->reasons as $option)
            <option value="{{ $option }}">{{ $option === ChangeReason::Other->value ? ChangeReason::Other->getLabel() : $option }}</option>
        @endforeach
    </select>
    @error('reason')<span class="{{ $error }}">{{ $message }}</span>@enderror
</div>

@if ($reason === ChangeReason::Other->value)
    <div>
        <label for="{{ $prefix }}-reason-other" class="{{ $label }}">{{ __('Wat is de reden?') }}</label>
        <input id="{{ $prefix }}-reason-other" type="text" wire:model="reasonOther" maxlength="500" class="{{ $field }}">
        @error('reasonOther')<span class="{{ $error }}">{{ $message }}</span>@enderror
    </div>
@endif

<div>
    <label for="{{ $prefix }}-explanation" class="{{ $label }}">{{ __('Toelichting') }}</label>
    <textarea id="{{ $prefix }}-explanation" wire:model="explanation" rows="4" maxlength="2000" class="{{ $field }}" placeholder="{{ $placeholder }}"></textarea>
    @error('explanation')<span class="{{ $error }}">{{ $message }}</span>@enderror
</div>
