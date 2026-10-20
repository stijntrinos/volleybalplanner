<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Bezoek aan de openbare website') }}</x-slot>
        <p>{{ __('Alleen bezoek met toestemming wordt gemeten. Bots en platformbeheerders tellen niet mee. Een bezoek is een browsersessie per dag; dit is geen telling van personen. De besloten planner valt buiten deze statistieken.') }}</p>
        <div class="mt-4 max-w-xs">
            <label for="analytics-period" class="mb-2 block text-sm font-medium">{{ __('Periode') }}</label>
            <x-filament::input.wrapper>
                <x-filament::input.select id="analytics-period" wire:model.live="period">
                    <option value="7">{{ __('Afgelopen 7 dagen') }}</option>
                    <option value="30">{{ __('Afgelopen 30 dagen') }}</option>
                    <option value="90">{{ __('Afgelopen 90 dagen') }}</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">{{ __('Bezoek per dag') }}</x-slot>
        @if ($timeline->sum('views') === 0)
            <p>{{ __('Nog geen bezoekersstatistieken in deze periode. Zodra bezoekers statistieken toestaan, verschijnen de metingen hier.') }}</p>
        @else
            <div class="max-h-96 overflow-auto">
                <table class="w-full text-sm">
                    <caption class="sr-only">{{ __('Paginaweergaven en bezoeken per dag') }}</caption>
                    <thead><tr class="text-left"><th class="p-2">{{ __('Dag') }}</th><th class="p-2">{{ __('Paginaweergaven') }}</th><th class="p-2">{{ __('Bezoeken') }}</th></tr></thead>
                    <tbody>
                        @foreach ($timeline as $day)
                            <tr class="border-t border-gray-200 dark:border-gray-700">
                                <th scope="row" class="p-2 text-left font-normal">{{ $day['date'] }}</th>
                                <td class="p-2">
                                    <div class="flex items-center gap-3">
                                        <span class="w-10 shrink-0 tabular-nums">{{ $day['views'] }}</span>
                                        <span aria-hidden="true" class="block h-3 rounded bg-primary-500" style="width: {{ round($day['views'] / $maxViews * 100) }}%; max-width: 20rem"></span>
                                    </div>
                                </td>
                                <td class="p-2 tabular-nums">{{ $day['visits'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>

    @foreach ([__('Populaire pagina’s') => [$pages, 'path'], __('Herkomst van paginaweergaven') => [$referrers, 'referrer_host'], __('Apparaten') => [$devices, 'device_type']] as $heading => [$rows, $column])
        <x-filament::section :heading="$heading">
            <table class="w-full text-sm">
                <thead><tr class="text-left"><th class="p-2">{{ $heading }}</th><th class="p-2 text-right">{{ __('Paginaweergaven') }}</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr class="border-t border-gray-200 dark:border-gray-700">
                            <th scope="row" class="break-all p-2 text-left font-normal">
                                @if ($column === 'device_type')
                                    {{ match ($row->$column) { 'mobile' => __('Mobiel'), 'tablet' => __('Tablet'), default => __('Desktop') } }}
                                @else
                                    {{ $row->$column ?? __('Direct / intern / onbekend') }}
                                @endif
                            </th>
                            <td class="p-2 text-right tabular-nums">{{ $row->views }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="p-2">{{ __('Nog geen metingen in deze periode.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
