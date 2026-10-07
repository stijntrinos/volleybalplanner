@php
    $toChoose = $report ? $this->toChoose : ['conflict' => [], 'removed' => []];
    $categoryLabels = [
        'new' => __('nieuw'), 'unchanged' => __('ongewijzigd'), 'autosync' => __('automatisch bijgewerkt'),
        'source_update' => __('bondswijziging'), 'conflict' => __('conflict'), 'removed' => __('verdwenen'),
    ];
@endphp
<div class="grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_minmax(28rem,.85fr)] 2xl:items-start">
    <form wire:submit="analyze" class="space-y-5">
        {{ $this->form }}
        @include('planner::filament.pages.import-planning.check-bar', [
            'heading' => __('2. Bestand controleren'), 'text' => __('Er worden nog geen gegevens opgeslagen.'), 'target' => 'analyze',
        ])
    </form>

    <div class="space-y-6">
        @if ($report)
            <x-filament::section
                :heading="__('3. Controleresultaat')"
                :description="__('Bekijk de aandachtspunten voordat je de planning importeert.')"
                icon="heroicon-o-clipboard-document-check"
            >
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['count' => $report['usable'], 'label' => __('Bruikbare wedstrijden')],
                        ['count' => count($report['skipped']), 'label' => __('Overgeslagen rijen')],
                        ['count' => count($report['past'] ?? []), 'label' => __('Gespeeld, genegeerd')],
                        ['count' => count($report['new_venues']), 'label' => __('Nieuwe sporthallen')],
                        ['count' => count($report['unknown_teams']), 'label' => __('Onbekende eigen teams')],
                    ] as $card)
                        <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                            <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['count'] }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                        </div>
                    @endforeach
                </div>

                @include('planner::filament.pages.import-planning.row-errors', ['rowErrors' => $report['skipped'], 'heading' => __('Overgeslagen rijen')])

                @if ($report['categories'])
                    <div class="mt-5">
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Verdeling importbestand') }}</h3>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($report['categories'] as $category => $items)
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                                    {{ count($items) }} {{ $categoryLabels[$category] ?? str_replace('_', ' ', $category) }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($report['name_variants'] ?? [])
                    <details class="mt-5 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ trans_choice(':count herkende naamvariant|:count herkende naamvarianten', count($report['name_variants'])) }}
                        </summary>
                        <ul class="mt-3 space-y-1 text-sm text-gray-500 dark:text-gray-400">
                            @foreach ($report['name_variants'] as $variant)
                                <li>{{ $variant['source'] }} → <span class="font-medium text-gray-700 dark:text-gray-300">{{ $variant['venue'] }}</span> ({{ $variant['method'] === 'alias' ? __('alias') : __('genormaliseerd') }})</li>
                            @endforeach
                        </ul>
                    </details>
                @endif

                @if ($toChoose['conflict'] || $toChoose['removed'])
                    <div class="mt-5 space-y-4 rounded-lg border border-warning-200 bg-warning-50/60 p-4 dark:border-warning-500/20 dark:bg-warning-500/5">
                        <p class="text-sm font-semibold text-warning-800 dark:text-warning-300">{{ __('Deze wedstrijden vragen een keuze') }}</p>

                        @if ($toChoose['conflict'])
                            <div>
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <p class="mr-auto text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {{ __('Bond en planning wijken allebei af (:count)', ['count' => count($toChoose['conflict'])]) }}
                                    </p>
                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="chooseForAll('conflict', 'source')">{{ __('Alles: bond volgen') }}</button>
                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="chooseForAll('conflict', 'manual')">{{ __('Alles: eigen planning') }}</button>
                                </div>
                                <ul class="space-y-2">
                                    @foreach ($toChoose['conflict'] as $item)
                                        @php $match = $item['match']; @endphp
                                        <li class="rounded-lg bg-white p-3 text-sm shadow-sm dark:bg-gray-900" wire:key="import-conflict-{{ $match->id }}">
                                            <p class="font-medium text-gray-950 dark:text-white">{{ $match->match_code }} · {{ $match->home_team_name }} – {{ $match->away_team_name }}</p>
                                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                                <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-2 dark:border-white/10">
                                                    <input type="radio" class="mt-1" wire:model.live="importChoices.{{ $match->id }}" value="manual">
                                                    <span>
                                                        <span class="block font-medium">{{ __('Eigen planning houden') }}</span>
                                                        <span class="block text-xs text-gray-500">{{ $match->date?->format('d-m-Y') }} {{ \Illuminate\Support\Str::substr((string) $match->time, 0, 5) }} · {{ $match->venue?->name }}</span>
                                                    </span>
                                                </label>
                                                <label class="flex cursor-pointer items-start gap-2 rounded-md border border-gray-200 p-2 dark:border-white/10">
                                                    <input type="radio" class="mt-1" wire:model.live="importChoices.{{ $match->id }}" value="source">
                                                    <span>
                                                        <span class="block font-medium">{{ __('Bond volgen') }}</span>
                                                        <span class="block text-xs text-gray-500">{{ \App\Planner\Support\DateFormat::date($item['row']['date'] ?? null) }} {{ $item['row']['time'] ?? '' }} · {{ $item['row']['venue'] ?? '' }}</span>
                                                    </span>
                                                </label>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if ($toChoose['removed'])
                            <div>
                                <div class="mb-2 flex flex-wrap items-center gap-2">
                                    <p class="mr-auto text-xs font-semibold uppercase tracking-wide text-gray-500">
                                        {{ __('Niet meer in het bronbestand (:count)', ['count' => count($toChoose['removed'])]) }}
                                    </p>
                                    <button type="button" class="text-xs font-medium text-primary-600 hover:underline" wire:click="chooseForAll('removed', 'keep')">{{ __('Alles behouden') }}</button>
                                    <button type="button" class="text-xs font-medium text-danger-600 hover:underline" wire:click="chooseForAll('removed', 'delete')">{{ __('Alles verwijderen') }}</button>
                                </div>
                                <ul class="space-y-2">
                                    @foreach ($toChoose['removed'] as $item)
                                        @php $match = $item['match']; @endphp
                                        <li class="flex flex-wrap items-center gap-3 rounded-lg bg-white p-3 text-sm shadow-sm dark:bg-gray-900" wire:key="import-removed-{{ $match->id }}">
                                            <span class="mr-auto">
                                                <span class="block font-medium text-gray-950 dark:text-white">{{ $match->match_code }} · {{ $match->home_team_name }} – {{ $match->away_team_name }}</span>
                                                <span class="block text-xs text-gray-500">{{ $match->date?->format('d-m-Y') }} {{ \Illuminate\Support\Str::substr((string) $match->time, 0, 5) }}</span>
                                            </span>
                                            <label class="flex cursor-pointer items-center gap-2"><input type="radio" wire:model.live="importChoices.{{ $match->id }}" value="keep"> {{ __('Behouden') }}</label>
                                            <label class="flex cursor-pointer items-center gap-2"><input type="radio" wire:model.live="importChoices.{{ $match->id }}" value="delete"> {{ __('Verwijderen') }}</label>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="mt-6 border-t border-gray-200 pt-5 dark:border-white/10">
                    <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ __('4. Import toepassen') }}</p>
                    <x-filament::button type="button" color="success" icon="heroicon-o-arrow-down-tray" wire:click="import" wire:loading.attr="disabled" wire:target="import" wire:confirm="{{ __('Weet je zeker dat je deze planning wilt importeren?') }}">
                        <span wire:loading.remove wire:target="import">{{ __('Planning importeren') }}</span>
                        <span wire:loading wire:target="import">{{ __('Planning importeren…') }}</span>
                    </x-filament::button>
                </div>
            </x-filament::section>
        @else
            @include('planner::filament.pages.import-planning.empty-state', [
                'icon' => 'heroicon-o-document-magnifying-glass',
                'heading' => __('Nog geen controleresultaat'),
                'text' => __('Upload links een bestand en voer eerst de controle uit. Daarna kun je de import veilig toepassen.'),
            ])
        @endif

        @if ($verification)
            <x-filament::section :heading="__('Verificatie na import')" :icon="$verification['equal'] ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle'">
                <div @class([
                    'rounded-lg p-4 text-sm font-medium',
                    'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $verification['equal'],
                    'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => ! $verification['equal'],
                ])>
                    {{ $verification['equal'] ? __('De database komt volledig overeen met het bronbestand.') : __(':count verschillen gevonden.', ['count' => count($verification['differences'])]) }}
                </div>
                @if (! $verification['equal'])
                    <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5">
                                <tr><th class="p-3">{{ __('Code') }}</th><th class="p-3">{{ __('Veld') }}</th><th class="p-3">{{ __('Bron') }}</th><th class="p-3">{{ __('Database') }}</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($verification['differences'] as $difference)
                                    <tr><td class="p-3 font-mono text-xs">{{ $difference['code'] }}</td><td class="p-3">{{ $difference['field'] }}</td><td class="p-3">{{ $difference['source'] }}</td><td class="p-3">{{ $difference['database'] }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-filament::section>
        @endif
    </div>
</div>
