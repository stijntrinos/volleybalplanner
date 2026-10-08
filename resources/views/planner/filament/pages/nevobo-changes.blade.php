<x-filament-panels::page>
    @php
        $statistics = $this->statistics;
        $groups = collect($this->filteredChanges)
            ->groupBy(fn (array $match) => $grouping === 'day'
                ? ($match['date'] ?: \App\Planner\Filament\Pages\NevoboChanges::UNPLANNED)
                : $match['home_team']);
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['count' => $statistics['matches'], 'label' => __('Wedstrijden gewijzigd'), 'icon' => 'heroicon-o-trophy', 'class' => 'bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400'],
            ['count' => $statistics['open'], 'label' => __('Nog te verwerken'), 'icon' => 'heroicon-o-exclamation-circle', 'class' => 'bg-warning-50 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400'],
            ['count' => $statistics['processed'], 'label' => __('Reeds verwerkt'), 'icon' => 'heroicon-o-check-circle', 'class' => 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'],
            ['count' => $statistics['total'], 'label' => __('Wijzigingen totaal'), 'icon' => 'heroicon-o-list-bullet', 'class' => 'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-400'],
        ] as $card)
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3">
                    <div class="rounded-lg p-2 {{ $card['class'] }}">
                        <x-filament::icon :icon="$card['icon']" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['count'] }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-filament::section
        :heading="__('Overzicht instellen')"
        :description="__('Kies een planningversie en bepaal welke wijzigingen je wilt controleren.')"
        icon="heroicon-o-funnel"
    >
        <div class="grid gap-5 xl:grid-cols-[minmax(16rem,1fr)_minmax(20rem,1.4fr)_auto] xl:items-end">
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Planningversie') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="versionId" :disabled="$this->versions->isEmpty()">
                        @forelse ($this->versions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @empty
                            <option value="">{{ __('Nog geen planningversies') }}</option>
                        @endforelse
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <div class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Status') }}</span>
                <div class="inline-flex w-full rounded-lg bg-gray-100 p-1 dark:bg-white/5">
                    @foreach ([
                        'open' => [__('Openstaand'), $statistics['open']],
                        'processed' => [__('Verwerkt'), $statistics['processed']],
                        'all' => [__('Alles'), $statistics['total']],
                    ] as $value => [$label, $count])
                        <button
                            type="button"
                            wire:click="$set('status', '{{ $value }}')"
                            @class([
                                'flex flex-1 items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition',
                                'bg-white text-primary-600 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-800 dark:text-primary-400 dark:ring-white/10' => $status === $value,
                                'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $status !== $value,
                            ])
                        >
                            {{ $label }}
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs tabular-nums dark:bg-white/10">{{ $count }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-wrap gap-2 xl:justify-end">
                <x-filament::button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel" color="success" icon="heroicon-o-table-cells">
                    {{ __('Excel') }}
                </x-filament::button>
                <x-filament::button wire:click="exportPdf" wire:loading.attr="disabled" wire:target="exportPdf" color="gray" icon="heroicon-o-document-text">
                    {{ __('PDF') }}
                </x-filament::button>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-white/10">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ trans_choice(':count wedstrijd zichtbaar met het huidige filter.|:count wedstrijden zichtbaar met het huidige filter.', $groups->sum(fn ($matches) => $matches->count())) }}
            </p>
            <div class="inline-flex rounded-lg bg-gray-100 p-1 dark:bg-white/5">
                @foreach (['team' => __('Per team'), 'day' => __('Per wedstrijddag')] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('grouping', '{{ $value }}')"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition',
                            'bg-white text-gray-950 shadow-sm dark:bg-gray-800 dark:text-white' => $grouping === $value,
                            'text-gray-500 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $grouping !== $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-filament::section>

    <div wire:loading.flex wire:target="versionId,status,grouping,markAll" class="items-center justify-center gap-2 rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
        <x-filament::loading-indicator class="h-5 w-5" />
        {{ __('Overzicht bijwerken…') }}
    </div>

    <div wire:loading.remove wire:target="versionId,status,grouping,markAll" class="space-y-6">
        <div class="flex gap-3 rounded-xl bg-primary-50 p-4 text-sm text-primary-800 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/20">
            <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0" />
            <p>{!! __('<strong>Bij Nevobo verwerkt</strong> registreert alleen dat je onze aangepaste planning ook bij Nevobo hebt doorgevoerd. De wedstrijdplanning zelf wordt niet gewijzigd.') !!}</p>
        </div>

        @forelse ($groups as $group => $matches)
            <x-filament::section
                :heading="$grouping === 'day' ? $this->formatGroupDate($group) : $group"
                :description="trans_choice(':count wedstrijd|:count wedstrijden', $matches->count())"
                collapsible
            >
                <div class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($matches as $match)
                        <article class="py-5 first:pt-0 last:pb-0" wire:key="nevobo-match-{{ $match['match_id'] }}">
                            @php
                                $open = collect($match['fields'])->where('processed', false)->count();
                                $markArguments = $match['match_id'].', '.($open > 0 ? 'true' : 'false');
                            @endphp
                            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $match['code'] }}</span>
                                        <h3 class="font-semibold text-gray-950 dark:text-white">
                                            {{ $match['home_team'] }} <span class="font-normal text-gray-400">–</span> {{ $match['away_team'] }}
                                        </h3>
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                        <span class="inline-flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-o-calendar-days" class="h-4 w-4" />
                                            {{ $match['date'] ? $this->formatValue('date', $match['date']) : __('Ongepland') }}
                                            @if ($match['time']) {{ __('om :time', ['time' => $match['time']]) }} @endif
                                        </span>
                                        @if ($match['venue'])
                                            <span class="inline-flex items-center gap-1.5">
                                                <x-filament::icon icon="heroicon-o-map-pin" class="h-4 w-4" />{{ $match['venue'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full bg-warning-50 px-2.5 py-1 text-xs font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                        {{ trans_choice(':count wijziging|:count wijzigingen', count($match['fields'])) }}
                                    </span>

                                    {{-- At Nevobo you enter everything of this match in one go, so
                                         there is deliberately no button per field. --}}
                                    <x-filament::button
                                        wire:click="markAll({{ $markArguments }})"
                                        wire:confirm="{{ $open > 0
                                            ? __('Bevestig dat je deze wedstrijd bij Nevobo hebt doorgevoerd. De wedstrijdplanning zelf wordt niet aangepast.')
                                            : __('Deze wedstrijd opnieuw als openstaand markeren?') }}"
                                        wire:loading.attr="disabled"
                                        wire:target="markAll({{ $markArguments }})"
                                        :color="$open > 0 ? 'success' : 'gray'"
                                        :icon="$open > 0 ? 'heroicon-o-check-badge' : 'heroicon-o-arrow-uturn-left'"
                                        size="sm"
                                    >
                                        {{ $open > 0 ? __('Bij Nevobo verwerkt') : __('Heropenen') }}
                                    </x-filament::button>
                                </div>
                            </div>

                            <div class="grid gap-3 2xl:grid-cols-2">
                                @foreach ($match['fields'] as $field)
                                    <div @class([
                                        'rounded-xl border p-4 transition',
                                        'border-success-200 bg-success-50/50 dark:border-success-500/20 dark:bg-success-500/5' => $field['processed'],
                                        'border-warning-200 bg-warning-50/40 dark:border-warning-500/20 dark:bg-warning-500/5' => ! $field['processed'],
                                    ])>
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div class="flex items-center gap-2 font-medium text-gray-950 dark:text-white">
                                                <x-filament::icon :icon="$this->fieldIcon($field['field'])" class="h-5 w-5 text-gray-400" />
                                                {{ $this->fieldLabel($field['field']) }}
                                            </div>
                                            <span @class([
                                                'inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium',
                                                'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $field['processed'],
                                                'bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => ! $field['processed'],
                                            ])>
                                                <span @class(['h-1.5 w-1.5 rounded-full', 'bg-success-500' => $field['processed'], 'bg-warning-500' => ! $field['processed']])></span>
                                                {{ $field['processed'] ? __('Verwerkt') : __('Openstaand') }}
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-3">
                                            <div class="min-w-0">
                                                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Oorspronkelijke Nevobo-planning') }}</p>
                                                <p class="truncate text-sm text-gray-600 line-through decoration-gray-400 dark:text-gray-300">{{ $this->formatValue($field['field'], $field['old']) }}</p>
                                            </div>
                                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-5 w-5 text-gray-400" />
                                            <div class="min-w-0">
                                                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-primary-500">{{ __('Onze aangepaste planning') }}</p>
                                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $this->formatValue($field['field'], $field['new']) }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            </x-filament::section>
        @empty
            <div class="rounded-xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400">
                    <x-filament::icon icon="heroicon-o-check-circle" class="h-7 w-7" />
                </div>
                <h2 class="font-semibold text-gray-950 dark:text-white">{{ __('Geen wijzigingen gevonden') }}</h2>
                <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('Er zijn geen Nevobo-wijzigingen die passen bij de gekozen planningversie en status.') }}</p>
                @if ($status !== 'all')
                    <button type="button" wire:click="$set('status', 'all')" class="mt-4 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ __('Alle wijzigingen bekijken') }}</button>
                @endif
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
