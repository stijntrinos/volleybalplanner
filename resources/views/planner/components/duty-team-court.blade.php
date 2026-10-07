@props([
    'path',
    'value' => null,
    'options' => [],
    'fieldErrors' => [],
    'fieldWarnings' => [],
    'label',
    'nevobo' => false,
])

@php
    $list = $nevobo ? [\App\Planner\Services\Duties\DutyPlanner::NEVOBO => \App\Planner\Services\Duties\DutyPlanner::NEVOBO] + $options : $options;
    $current = trim((string) $value);
    // A value from an earlier schedule or import should not silently
    // disappear because the team is no longer included in duties.
    if ($current !== '' && ! array_key_exists($current, $list)) {
        $list[$current] = __(':team (onbekend)', ['team' => $current]);
    }
@endphp

{{-- The key contains the path. When a row shifts in the draft, morphing would
     otherwise reuse this select with a new wire:model while Livewire keeps the
     old binding, so a choice wrote into the row that used to be at this index. --}}
<select
    wire:key="duty-field-{{ $path }}"
    wire:model.live="{{ $path }}"
    aria-label="{{ $label }}"
    @class([
        'fi-select-input w-full rounded-lg dark:bg-gray-800',
        'border-danger-400 focus:border-danger-500 focus:ring-danger-500' => $fieldErrors,
        'border-warning-400 focus:border-warning-500 focus:ring-warning-500' => ! $fieldErrors && $fieldWarnings,
        'border-gray-300' => ! $fieldErrors && ! $fieldWarnings,
    ])
>
    <option value="">{{ __('— niet ingevuld —') }}</option>
    @foreach ($list as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}">{{ $optionLabel }}</option>
    @endforeach
</select>

@foreach ($fieldErrors as $reason)
    <p class="mt-1.5 flex gap-1 text-xs text-danger-600 dark:text-danger-400">
        <x-filament::icon icon="heroicon-o-exclamation-circle" class="h-4 w-4 shrink-0" />{{ $reason }}
    </p>
@endforeach

@foreach ($fieldWarnings as $reason)
    <p class="mt-1.5 flex gap-1 text-xs text-warning-600 dark:text-warning-400">
        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4 shrink-0" />{{ $reason }}
    </p>
@endforeach
