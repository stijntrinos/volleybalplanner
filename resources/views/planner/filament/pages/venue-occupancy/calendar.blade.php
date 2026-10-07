@foreach ($this->calendar as $month => $rows)
    <x-filament::section :heading="$month" icon="heroicon-o-calendar-days" collapsible>
        <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
            @foreach ($rows as $row)
                @php $color = $this->statusColor($row['status']); @endphp
                <div @class([
                    'rounded-xl border p-3',
                    'border-success-200 bg-success-50/50 dark:border-success-500/20 dark:bg-success-500/5' => $color === 'success',
                    'border-warning-200 bg-warning-50/50 dark:border-warning-500/20 dark:bg-warning-500/5' => $color === 'warning',
                    'border-danger-200 bg-danger-50/50 dark:border-danger-500/20 dark:bg-danger-500/5' => $color === 'danger',
                    'border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-white/5' => $color === 'gray',
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['day'] }} {{ \App\Planner\Support\DateFormat::date($row['date']) }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $row['venue'] }}</p>
                        </div>
                        <x-filament::badge :color="$color" size="xs">{{ $this->statusLabel($row['status']) }}</x-filament::badge>
                    </div>
                    <dl class="mt-2 space-y-0.5 text-xs text-gray-600 dark:text-gray-300">
                        <div class="flex justify-between gap-2">
                            <dt>{{ __('Beschikbaar') }}</dt>
                            <dd>{{ $row['available_from'] ? $row['available_from'].' – '.$row['available_until'] : '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt>{{ __('Wedstrijden') }}</dt>
                            <dd>{{ $row['matches'] ? $row['matches'].' · '.$row['first'].' – '.$row['end_of_last'] : __('geen') }}</dd>
                        </div>
                    </dl>
                    @if ($row['note'])
                        <p class="mt-2 border-t border-gray-200 pt-2 text-xs italic text-gray-500 dark:border-white/10 dark:text-gray-400">{{ $row['note'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
@endforeach
@if ($this->calendarTotal > $calendarCount)
    <div class="flex items-center justify-center gap-3 text-sm">
        <span class="text-gray-500 dark:text-gray-400">{{ __(':shown van :total dagen getoond', ['shown' => $calendarCount, 'total' => $this->calendarTotal]) }}</span>
        <x-filament::button size="sm" color="gray" icon="heroicon-m-chevron-down" wire:click="showMoreCalendar" wire:loading.attr="disabled" wire:target="showMoreCalendar">
            {{ __('Meer tonen') }}
        </x-filament::button>
    </div>
@endif
