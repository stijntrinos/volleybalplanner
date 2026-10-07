{{-- Result of a trainings or availability analyze(): valid, skipped, errors. --}}
<x-filament::section :heading="__('3. Controleresultaat')" icon="heroicon-o-clipboard-document-check">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-lg bg-success-50 p-4 dark:bg-success-500/5"><p class="text-2xl font-semibold text-success-600">{{ $result['valid'] }}</p><p class="text-xs text-gray-500">{{ $validLabel }}</p></div>
        <div class="rounded-lg bg-gray-50 p-4 dark:bg-white/5"><p class="text-2xl font-semibold text-gray-600 dark:text-gray-300">{{ $result['skipped'] }}</p><p class="text-xs text-gray-500">{{ $skippedLabel }}</p></div>
        <div class="rounded-lg bg-danger-50 p-4 dark:bg-danger-500/5"><p class="text-2xl font-semibold text-danger-600">{{ count($result['errors']) }}</p><p class="text-xs text-gray-500">{{ __('Rijen met fouten') }}</p></div>
    </div>
    @include('planner::filament.pages.import-planning.row-errors', ['rowErrors' => $result['errors']])
    @if ($result['valid'] > 0)
        <div class="mt-6 border-t border-gray-200 pt-5 dark:border-white/10">
            <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ __('4. Import toepassen') }}</p>
            <x-filament::button color="success" icon="heroicon-o-arrow-down-tray" wire:click="{{ $action }}" wire:loading.attr="disabled" wire:target="{{ $action }}" wire:confirm="{{ $confirm }}">{{ $button }}</x-filament::button>
        </div>
    @endif
</x-filament::section>
