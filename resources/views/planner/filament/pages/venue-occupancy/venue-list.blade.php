@php
    use App\Planner\Support\DateFormat;

    $shown = $this->shown($venue['venue_id']);
@endphp
<x-filament::section
    :heading="$venue['venue']"
    :description="trans_choice(':count dag|:count dagen', count($venue['rows']))"
    icon="heroicon-o-building-office-2"
    collapsible
>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <th class="py-2 pr-3 font-medium">{{ __('Datum') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Beschikbaar') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Wedstrijden') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Eerste') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Laatste eindigt') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                    <th class="py-2 font-medium">{{ __('Notitie') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach (array_slice($venue['rows'], 0, $shown) as $row)
                    @php
                        $color = $this->statusColor($row['status']);
                        $open = $row['details'] !== [] && $this->dayIsOpen($venue['venue_id'], $row['date']);
                    @endphp
                    <tr
                        wire:key="occupancy-{{ $venue['venue_id'] }}-{{ $row['date'] }}"
                        @if ($row['details'] !== []) wire:click="toggleDay({{ $venue['venue_id'] }}, '{{ $row['date'] }}')" class="cursor-pointer hover:bg-gray-50 dark:hover:bg-white/5" @endif
                    >
                        <td class="py-2 pr-3 font-medium text-gray-950 dark:text-white">
                            <span class="flex items-center gap-1.5">
                                @if ($row['details'] !== [])
                                    <x-filament::icon icon="heroicon-m-chevron-right" @class(['h-4 w-4 text-gray-400 transition', 'rotate-90' => $open]) />
                                @else
                                    <span class="w-4"></span>
                                @endif
                                {{ $row['day'] }} {{ DateFormat::date($row['date']) }}
                            </span>
                        </td>
                        <td class="py-2 pr-3 text-gray-600 dark:text-gray-300">
                            {{ $row['available_from'] ? $row['available_from'].' – '.$row['available_until'] : '—' }}
                        </td>
                        <td class="py-2 pr-3 text-gray-600 dark:text-gray-300">{{ $row['matches'] }}</td>
                        <td class="py-2 pr-3 text-gray-600 dark:text-gray-300">{{ $row['first'] ?? '—' }}</td>
                        <td class="py-2 pr-3 text-gray-600 dark:text-gray-300">{{ $row['end_of_last'] ?? '—' }}</td>
                        <td class="py-2 pr-3">
                            <x-filament::badge :color="$color">{{ $this->statusLabel($row['status']) }}</x-filament::badge>
                        </td>
                        <td class="py-2 text-gray-500 dark:text-gray-400">{{ $row['note'] ?: '—' }}</td>
                    </tr>

                    @if ($open)
                        <tr wire:key="occupancy-details-{{ $venue['venue_id'] }}-{{ $row['date'] }}">
                            <td colspan="7" class="bg-gray-50 px-4 py-3 dark:bg-white/5">
                                <ul class="space-y-1.5">
                                    @foreach ($row['details'] as $detail)
                                        <li class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                                            <span class="font-mono text-gray-500 dark:text-gray-400">{{ $detail['code'] }}</span>
                                            <span class="font-medium text-gray-950 dark:text-white">{{ $detail['home'] }} – {{ $detail['away'] }}</span>
                                            <span>{{ $detail['start'] ?? '—' }} – {{ $detail['end'] ?? '—' }} ({{ $detail['duration'] }})</span>
                                            @if ($detail['court'])
                                                <span class="rounded bg-gray-200 px-1.5 py-0.5 dark:bg-white/10">{{ __('Veld :court', ['court' => $detail['court']]) }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
    @if (count($venue['rows']) > $shown)
        <div class="mt-3 flex items-center justify-between gap-3 border-t border-gray-100 pt-3 text-sm dark:border-white/5">
            <span class="text-gray-500 dark:text-gray-400">{{ __(':shown van :total dagen getoond', ['shown' => $shown, 'total' => count($venue['rows'])]) }}</span>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-down"
                wire:click="showMore({{ $venue['venue_id'] }})" wire:loading.attr="disabled" wire:target="showMore({{ $venue['venue_id'] }})">
                {{ __('Meer tonen') }}
            </x-filament::button>
        </div>
    @endif
</x-filament::section>
