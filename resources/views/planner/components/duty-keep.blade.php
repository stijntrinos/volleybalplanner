@props(['locked' => false, 'origin' => '', 'action', 'task' => null])

@php
    $task ??= __('deze taak');
@endphp

{{-- Every field has its own lock: referee and scorer can be locked separately. --}}
<div class="flex shrink-0 items-center gap-0.5">
    <x-filament::icon-button
        :icon="$locked ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open'"
        :color="$locked ? 'primary' : 'gray'"
        size="sm"
        :tooltip="$locked
            ? __('Vastgezet: :task blijft staan bij opnieuw genereren. Klik om vrij te geven.', ['task' => $task])
            : __('Vastzetten: :task blijft dan staan bij opnieuw genereren.', ['task' => $task])"
        :label="$locked ? __('Vastgezet: :task', ['task' => $task]) : __('Vastzetten: :task', ['task' => $task])"
        wire:click="{{ $action }}"
        wire:loading.attr="disabled"
    />

    <x-filament::icon-button
        icon="heroicon-o-information-circle"
        size="sm"
        color="gray"
        :tooltip="$origin"
        :label="$origin"
        tag="span"
    />
</div>
