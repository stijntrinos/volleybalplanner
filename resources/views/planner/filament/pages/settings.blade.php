<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6 pb-20">
        {{ $this->form }}

        <div class="sticky bottom-4 z-20 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white/95 px-4 py-3 shadow-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-gray-900/95">
            <p class="hidden text-sm text-gray-500 sm:block">{{ __('Wijzigingen worden pas actief nadat je ze opslaat.') }}</p>
            <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="save" class="w-full justify-center sm:w-auto">
                {{ __('Instellingen opslaan') }}
            </x-filament::button>
        </div>
    </form>
    <x-filament-actions::modals />
</x-filament-panels::page>
