<x-filament-panels::page>
    @php
        $totals = $this->totals;
        $summary = $this->summary;
        $filtered = $this->filteredTallies;
        $withPoints = $this->withVolunteerPoints();
        $withVolleystars = $this->withVolleystars();
        $unit = $withPoints ? __('Taakpunten') : __('Taken');
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-primary-50 p-2 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400"><x-filament::icon icon="heroicon-o-user-group" class="h-5 w-5" /></div>
                <div><p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['teams'] }}</p><p class="text-sm text-gray-500">{{ __('Teams ingepland') }}</p></div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-info-50 p-2 text-info-600 dark:bg-info-500/10 dark:text-info-400"><x-filament::icon icon="heroicon-o-calculator" class="h-5 w-5" /></div>
                <div><p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['average'], 1, ',', '.') }}</p><p class="text-sm text-gray-500">{{ __(':unit gemiddeld', ['unit' => $unit]) }}</p></div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-warning-50 p-2 text-warning-600 dark:bg-warning-500/10 dark:text-warning-400"><x-filament::icon icon="heroicon-o-arrow-trending-up" class="h-5 w-5" /></div>
                <div class="min-w-0"><p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $summary['maximum'] }}</p><p class="truncate text-sm text-gray-500" title="{{ $summary['busiest_team'] }}">{{ __('Hoogste') }} · {{ $summary['busiest_team'] ?: '—' }}</p></div>
            </div>
        </div>
        <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-success-50 p-2 text-success-600 dark:bg-success-500/10 dark:text-success-400"><x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-5 w-5" /></div>
                <div><p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $totals['total'] }}</p><p class="text-sm text-gray-500">{{ __(':unit totaal', ['unit' => $unit]) }}</p></div>
            </div>
        </div>
    </div>

    <x-filament::section
        :heading="__('Takenverdeling')"
        :description="$withPoints ? __('Alle diensten zijn omgerekend met de vrijwilligerspunten uit de plannerinstellingen.') : __('Elke dienst telt als één taak.')"
        icon="heroicon-o-scale"
    >
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-[minmax(12rem,1fr)_minmax(9rem,.6fr)_minmax(9rem,.6fr)_minmax(12rem,1fr)_auto_auto] lg:items-end">
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Planningversie') }}
                <select wire:model.live="versionId" class="fi-select-input rounded-lg border-gray-300 dark:bg-gray-800">
                    @foreach ($this->versions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                </select>
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Van') }}
                <input type="date" wire:model.live="fromDate" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                @error('fromDate')<span class="text-xs text-danger-600">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Tot en met') }}
                <input type="date" wire:model.live="toDate" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                @error('toDate')<span class="text-xs text-danger-600">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Team zoeken') }}
                <div class="relative">
                    <x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                    <input wire:model.live.debounce.250ms="search" type="search" placeholder="{{ __('Bijvoorbeeld Dames 1') }}" class="w-full rounded-lg border-gray-300 py-2 pl-10 pr-3 text-sm dark:bg-gray-800">
                </div>
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Sorteren op') }}
                <select wire:model.live="sort" class="fi-select-input rounded-lg border-gray-300 dark:bg-gray-800">
                    <option value="team_asc">{{ __('Teamnaam') }}</option>
                    @if ($withPoints)<option value="percentage_desc">{{ __('Grootste tekort') }}</option>@endif
                    <option value="total_desc">{{ __('Meeste taken') }}</option>
                    <option value="referee_desc">{{ __('Meeste fluitbeurten') }}</option>
                    <option value="scorer_desc">{{ __('Meeste telbeurten') }}</option>
                    <option value="hall_duty_desc">{{ __('Meeste zaaldiensten') }}</option>
                    @if ($withVolleystars)<option value="volleystars_desc">{{ __('Meeste VolleyStars-taken') }}</option>@endif
                </select>
            </label>
            <x-filament::button wire:click="exportImage" icon="heroicon-o-photo" color="gray" wire:loading.attr="disabled" wire:target="exportImage">
                <span wire:loading.remove wire:target="exportImage">{{ __('Als afbeelding') }}</span>
                <span wire:loading wire:target="exportImage">{{ __('Afbeelding maken…') }}</span>
            </x-filament::button>
        </div>

        <div class="mt-5 flex flex-wrap gap-x-5 gap-y-2 border-t border-gray-200 pt-4 text-sm dark:border-white/10">
            @foreach (array_filter([
                ['label' => __('Fluiten'), 'count' => $totals['referee'], 'color' => 'bg-primary-500'],
                ['label' => __('Tellen'), 'count' => $totals['scorer'], 'color' => 'bg-info-500'],
                ['label' => __('Zaaldienst'), 'count' => $totals['hall_duty'], 'color' => 'bg-warning-500'],
                $withVolleystars ? ['label' => __('VolleyStars'), 'count' => $totals['volleystars'], 'color' => 'bg-success-500'] : null,
            ]) as $legend)
                <span class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300"><span class="h-2.5 w-2.5 rounded-full {{ $legend['color'] }}"></span>{{ $legend['label'] }} <strong>{{ $legend['count'] }}</strong></span>
            @endforeach
        </div>
    </x-filament::section>

    <div wire:loading.flex wire:target="versionId,fromDate,toDate,search,sort" class="items-center justify-center gap-2 rounded-xl bg-white p-6 text-sm text-gray-500 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <x-filament::loading-indicator class="h-5 w-5" /> {{ __('Overzicht bijwerken…') }}
    </div>

    <x-filament::section wire:loading.remove wire:target="versionId,fromDate,toDate,search,sort" :heading="trans_choice(':count team|:count teams', count($filtered))" :description="__('Laagste belasting: :min · hoogste belasting: :max', ['min' => $summary['minimum'], 'max' => $summary['maximum']])">
        @if ($filtered)
            <div class="overflow-x-auto">
                <table class="w-full min-w-[60rem] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <th class="px-3 py-3">{{ __('Team') }}</th>
                            @if ($withPoints)
                                <th class="px-3 py-3 text-center">{{ __('Verbruikt') }}</th>
                                <th class="px-3 py-3 text-center">{{ __('Geleverd') }}</th>
                            @endif
                            <th class="px-3 py-3 text-center">{{ __('Fluiten') }}</th>
                            <th class="px-3 py-3 text-center">{{ __('Tellen') }}</th>
                            <th class="px-3 py-3 text-center">{{ __('Zaaldienst') }}</th>
                            @if ($withVolleystars)<th class="px-3 py-3 text-center">{{ __('VolleyStars') }}</th>@endif
                            @if ($withPoints)<th class="px-3 py-3">{{ __('Percentage') }}</th>@endif
                            <th class="px-3 py-3 text-right">{{ $unit }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @foreach ($filtered as $row)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[.025]" wire:key="duty-team-{{ md5($row['team']) }}">
                                <td class="px-3 py-4 font-semibold text-gray-950 dark:text-white">{{ $row['team'] }}</td>
                                @if ($withPoints)
                                    <td class="px-3 py-4 text-center font-medium">{{ $row['used'] ?? 0 }}</td>
                                    <td class="px-3 py-4 text-center font-medium">{{ $row['earned'] ?? 0 }}</td>
                                @endif
                                <td class="px-3 py-4 text-center"><span class="inline-flex min-w-8 justify-center rounded-full bg-primary-50 px-2.5 py-1 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">{{ $row['referee'] }}</span></td>
                                <td class="px-3 py-4 text-center"><span class="inline-flex min-w-8 justify-center rounded-full bg-info-50 px-2.5 py-1 font-medium text-info-700 dark:bg-info-500/10 dark:text-info-400">{{ $row['scorer'] }}</span></td>
                                <td class="px-3 py-4 text-center"><span class="inline-flex min-w-8 justify-center rounded-full bg-warning-50 px-2.5 py-1 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">{{ $row['hall_duty'] }}</span></td>
                                @if ($withVolleystars)
                                    <td class="px-3 py-4 text-center"><span class="inline-flex min-w-8 justify-center rounded-full bg-success-50 px-2.5 py-1 font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ $row['volleystars'] ?? 0 }}</span></td>
                                @endif
                                @if ($withPoints)
                                    @php($percentage = (float) ($row['percentage'] ?? 0))
                                    <td class="w-52 px-3 py-4">
                                        <span @class([
                                            'inline-flex rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $percentage > 0,
                                            'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $percentage < 0,
                                            'bg-gray-100 text-gray-600 dark:bg-white/5 dark:text-gray-300' => $percentage == 0,
                                        ])>
                                            {{ $this->percentageLabel($percentage) }}
                                        </span>
                                    </td>
                                @endif
                                <td class="px-3 py-4 text-right text-base font-bold text-gray-950 dark:text-white">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 bg-gray-50 font-semibold text-gray-950 dark:border-white/20 dark:bg-white/[.03] dark:text-white">
                            <td class="px-3 py-4">{{ __('Alle teams') }}</td>
                            @if ($withPoints)
                                <td class="px-3 py-4 text-center">{{ $totals['used'] }}</td>
                                <td class="px-3 py-4 text-center">{{ $totals['earned'] }}</td>
                            @endif
                            <td class="px-3 py-4 text-center">{{ $totals['referee'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $totals['scorer'] }}</td>
                            <td class="px-3 py-4 text-center">{{ $totals['hall_duty'] }}</td>
                            @if ($withVolleystars)<td class="px-3 py-4 text-center">{{ $totals['volleystars'] }}</td>@endif
                            @if ($withPoints)<td></td>@endif
                            <td class="px-3 py-4 text-right text-base">{{ $totals['total'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="py-10 text-center"><x-filament::icon icon="heroicon-o-magnifying-glass" class="mx-auto mb-3 h-8 w-8 text-gray-400" /><p class="font-medium text-gray-950 dark:text-white">{{ __('Geen teams gevonden') }}</p><button type="button" wire:click="$set('search', '')" class="mt-2 text-sm font-medium text-primary-600 dark:text-primary-400">{{ __('Zoekopdracht wissen') }}</button></div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
