<x-filament-panels::page>
    <form wire:submit="link" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" icon="heroicon-o-link" wire:loading.attr="disabled" wire:target="link">
                {{ __('Koppelen') }}
            </x-filament::button>
        </div>
    </form>
    <x-filament-actions::modals />
</x-filament-panels::page>
