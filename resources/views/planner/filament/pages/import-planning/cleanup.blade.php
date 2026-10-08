<x-filament::section
    :heading="__('Importgegevens opschonen')"
    :description="__('Verwijder oude brongegevens per categorie. De gepubliceerde planning en handmatig beheerde sporthallen en velden blijven altijd behouden.')"
    icon="heroicon-o-trash"
>
    <div class="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/5 dark:text-warning-300">
        <div class="flex gap-3"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" /><p>{{ __('Selecteer alleen categorieën die opnieuw vanuit een bronbestand mogen worden opgebouwd. Verwijderen kan niet automatisch ongedaan worden gemaakt.') }}</p></div>
    </div>

    @if ($publishedVersionProtected)
        <div class="mb-5 rounded-lg border border-success-200 bg-success-50 p-4 text-sm text-success-800 dark:border-success-500/20 dark:bg-success-500/5 dark:text-success-300">
            <div class="flex gap-3"><x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5 shrink-0" /><p>{{ __('De gepubliceerde planningversie en alles wat daaraan hangt wordt overgeslagen. De aantallen hieronder zijn exclusief die versie.') }}</p></div>
        </div>
    @endif

    @if (($cleanupSelection['versions'] ?? false) && $cleanupCascade)
        <div class="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-500/20 dark:bg-danger-500/5 dark:text-danger-300">
            <div class="flex gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                <div>
                    <p class="font-semibold">{{ __('Planningversies verwijderen neemt onderliggende gegevens mee') }}</p>
                    <p class="mt-1">{{ __('Ook zonder die categorieën aan te vinken verdwijnt hiermee:') }}
                        {{ collect($cleanupCascade)->map(fn ($count, $category) => $count.' '.\Illuminate\Support\Str::lower($cleanupCategories[$category] ?? $category))->join(', ', ' '.__('en').' ') }}.
                    </p>
                </div>
            </div>
        </div>
    @endif
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($cleanupCategories as $key => $label)
            <label @class([
                'flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition',
                'border-danger-300 bg-danger-50/50 dark:border-danger-500/30 dark:bg-danger-500/5' => $cleanupSelection[$key] ?? false,
                'border-gray-200 hover:border-gray-300 dark:border-white/10 dark:hover:border-white/20' => ! ($cleanupSelection[$key] ?? false),
            ])>
                <x-filament::input.checkbox wire:model.live="cleanupSelection.{{ $key }}" />
                <span><strong class="block text-sm text-gray-950 dark:text-white">{{ $label }}</strong><small class="text-gray-500 dark:text-gray-400">{{ trans_choice(':count record aanwezig|:count records aanwezig', $cleanupCounts[$key] ?? 0) }}</small></span>
            </label>
        @endforeach
    </div>
    <div class="mt-6 flex justify-end border-t border-gray-200 pt-5 dark:border-white/10">
        <x-filament::button color="danger" icon="heroicon-o-trash" wire:click="cleanup" wire:loading.attr="disabled" wire:target="cleanup" wire:confirm="{{ __('Weet je zeker dat je de geselecteerde importgegevens definitief wilt verwijderen?') }}">{{ __('Geselecteerde gegevens verwijderen') }}</x-filament::button>
    </div>
</x-filament::section>
