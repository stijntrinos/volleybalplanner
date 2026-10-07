@use(App\Planner\Support\DateFormat)

<div class="max-h-96 overflow-y-auto">
    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($groups as $group)
            @php($match = $group['match'])
            <li class="py-3 first:pt-0 last:pb-0">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $match?->date ? DateFormat::withWeekday($match->date, short: true) : __('Zonder datum') }}
                    @if ($match?->time)
                        · {{ substr((string) $match->time, 0, 5) }}
                    @endif
                </p>

                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ trim(($match?->home_team_name ?? '').' - '.($match?->away_team_name ?? ''), ' -') ?: __('Onbekende wedstrijd') }}
                    @if ($match?->venue)
                        · {{ $match->venue->name }}@if ($match->court), {{ __('veld :court', ['court' => $match->court->name]) }}@endif @if ($match->secondCourt)+ {{ $match->secondCourt->name }}@endif
                    @endif
                </p>

                <ul class="mt-1.5 space-y-1">
                    @foreach ($group['messages'] as $message)
                        <li class="flex items-start gap-1.5 text-sm text-danger-600 dark:text-danger-400">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                            <span>{{ $message }}</span>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>
</div>
