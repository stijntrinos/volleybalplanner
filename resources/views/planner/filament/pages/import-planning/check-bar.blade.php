<div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="mr-auto">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }}</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $text }}</p>
    </div>
    <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled" wire:target="{{ $target }}">
        <span wire:loading.remove wire:target="{{ $target }}">{{ __('Controle uitvoeren') }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ __('Bestand controleren…') }}</span>
    </x-filament::button>
</div>
