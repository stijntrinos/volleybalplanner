@php
    use App\Planner\Services\VenueOccupancyService;
    use App\Planner\Support\DateFormat;

    $overview = $this->overview;
    $active = $this->activeFilter();

    $quickFilters = [
        'all' => __('Alles'),
        'match_days' => __('Alleen wedstrijddagen'),
        'unavailable' => __('Alleen niet-beschikbaar'),
        'conflicts' => __('Alleen conflicten'),
    ];
@endphp

<x-filament-panels::page>
    <x-filament::section
        :heading="__('Periode en filters')"
        :description="__('Kies de planningversie en de periode; de tabellen en downloads volgen deze selectie.')"
        icon="heroicon-o-adjustments-horizontal"
    >
        <div class="grid gap-4 lg:grid-cols-4">
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Planningversie') }}
                <select wire:model.live="versionId" class="fi-select-input rounded-lg border-gray-300 dark:bg-gray-800">
                    @foreach ($this->versions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Van') }}
                <input type="text" wire:model.live.debounce.500ms="fromDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" class="rounded-lg border-gray-300 dark:bg-gray-800">
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Tot en met') }}
                <input type="text" wire:model.live.debounce.500ms="toDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" class="rounded-lg border-gray-300 dark:bg-gray-800">
            </label>
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Sporthal') }}
                <select wire:model.live="venueId" class="fi-select-input rounded-lg border-gray-300 dark:bg-gray-800">
                    <option value="">{{ __('Alle sporthallen') }}</option>
                    @foreach ($this->venues as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="mt-5 grid gap-4 border-t border-gray-200 pt-5 dark:border-white/10 xl:grid-cols-[minmax(0,auto)_minmax(0,1fr)] xl:items-start">
            <div>
                <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">{{ __('Snelfilter') }}</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($quickFilters as $key => $label)
                        <button
                            type="button"
                            wire:click="chooseFilter('{{ $key }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition',
                                'bg-primary-600 text-white' => $active === $key,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10' => $active !== $key,
                            ])
                        >{{ $label }}</button>
                    @endforeach
                    @if ($active === 'custom')
                        <span class="self-center rounded-lg bg-info-50 px-3 py-1.5 text-sm font-medium text-info-700 dark:bg-info-500/10 dark:text-info-400">{{ __('Eigen selectie') }}</span>
                    @endif
                </div>
            </div>

            <div>
                <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">{{ __('Statussen') }}</p>
                <div class="flex flex-wrap gap-x-5 gap-y-2">
                    @foreach (VenueOccupancyService::statusLabels() as $status => $label)
                        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" value="{{ $status }}" wire:model.live="statuses" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Niets aangevinkt betekent: alle statussen tonen.') }}</p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 pt-5 dark:border-white/10">
            <div class="flex flex-wrap items-center gap-5">
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    <input type="checkbox" wire:model.live="matchDaysOnly" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    {{ __('Alleen dagen met wedstrijden') }}
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-950 dark:text-white">
                    <input type="checkbox" wire:model.live="withDetails" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800">
                    {{ __('Wedstrijddetails meenemen in de download') }}
                </label>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="excel" wire:loading.attr="disabled" wire:target="excel" color="success" icon="heroicon-o-table-cells">
                    <span wire:loading.remove wire:target="excel">{{ __('Excel') }}</span><span wire:loading wire:target="excel">{{ __('Maken…') }}</span>
                </x-filament::button>
                <x-filament::button wire:click="pdf" wire:loading.attr="disabled" wire:target="pdf" color="gray" icon="heroicon-o-document-text">
                    <span wire:loading.remove wire:target="pdf">{{ __('PDF') }}</span><span wire:loading wire:target="pdf">{{ __('Maken…') }}</span>
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="rounded-full bg-gray-100 px-3 py-1 font-medium text-gray-700 dark:bg-white/5 dark:text-gray-300">
                {{ trans_choice(':count dag|:count dagen', $overview['total']) }}
            </span>
            <span @class([
                'rounded-full px-3 py-1 font-medium',
                'bg-danger-50 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $overview['conflicts'] > 0,
                'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $overview['conflicts'] === 0,
            ])>
                {{ __(':count met conflict', ['count' => $overview['conflicts']]) }}
            </span>
            <span class="text-gray-500 dark:text-gray-400">
                {{ __(':from t/m :to', ['from' => DateFormat::date($overview['from']), 'to' => DateFormat::date($overview['to'])]) }}
            </span>
        </div>

        <div class="flex rounded-lg bg-gray-100 p-1 dark:bg-white/5">
            @foreach (['list' => __('Lijst'), 'calendar' => __('Kalender')] as $key => $label)
                <button
                    type="button"
                    wire:click="$set('display', '{{ $key }}')"
                    @class([
                        'rounded-md px-4 py-1.5 text-sm font-medium transition',
                        'bg-white text-gray-950 shadow-sm dark:bg-gray-700 dark:text-white' => $display === $key,
                        'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200' => $display !== $key,
                    ])
                >{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($overview['venues'] === [])
        <x-filament::section>
            <div class="py-8 text-center">
                <x-filament::icon icon="heroicon-o-building-office-2" class="mx-auto h-8 w-8 text-gray-400" />
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ __('Geen dagen gevonden voor deze periode en filters.') }}</p>
            </div>
        </x-filament::section>
    @elseif ($display === 'list')
        @foreach ($overview['venues'] as $venue)
            @include('planner::filament.pages.venue-occupancy.venue-list', ['venue' => $venue])
        @endforeach
    @else
        @include('planner::filament.pages.venue-occupancy.calendar')
    @endif
</x-filament-panels::page>
