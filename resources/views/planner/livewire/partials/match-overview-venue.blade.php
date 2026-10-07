@php use App\Planner\Support\DateFormat; @endphp

{{-- One venue on one day of the public match overview. --}}
<div class="mt-3 border border-gray-200 bg-white">
    <h3 class="flex flex-wrap items-baseline gap-x-2 border-b border-gray-200 bg-gray-100 px-4 py-2 md:px-5">
        <span class="text-sm font-semibold text-gray-900">{{ $venueGroup['name'] ?? __('Sporthal nog niet bekend') }}</span>
        @if ($venueGroup['city'])
            <span class="text-xs text-gray-500">{{ $venueGroup['city'] }}</span>
        @endif
    </h3>

    @if ($venueGroup['hall_duties']->isNotEmpty())
        <ul class="planner-tint-primary planner-accent-border divide-y divide-white/60 border-b">
            @foreach ($venueGroup['hall_duties'] as $duty)
                <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-4 py-2 text-sm md:px-5">
                    <span class="font-semibold text-gray-900">{{ __('Zaaldienst') }} · {{ $duty->team?->code ?? __('Nog niet ingedeeld') }}</span>
                    <span class="text-gray-600">
                        {{ DateFormat::time($duty->start_time) }}-{{ DateFormat::time($duty->end_time) }}
                    </span>
                    @if ($duty->notes)
                        <span class="text-gray-600">{{ $duty->notes }}</span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($venueGroup['volleystars_tasks']->isNotEmpty())
        <div class="planner-accent-border border-b bg-gray-50">
            <h4 class="px-4 pt-2 text-xs font-semibold uppercase tracking-widest text-gray-500 md:px-5">VolleyStars</h4>
            <ul class="divide-y divide-white/60">
                @foreach ($venueGroup['volleystars_tasks'] as $task)
                    <li class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 px-4 py-2 text-sm md:px-5">
                        <span class="font-semibold text-gray-900">{{ $task->role }} · {{ $task->team ?? __('Nog niet ingedeeld') }}</span>
                        <span class="text-gray-600">
                            {{ DateFormat::time($task->start) }}-{{ DateFormat::time($task->end) }}
                        </span>
                        @if ($task->court)
                            <span class="text-gray-600">{{ __('Veld :court', ['court' => $task->court]) }}</span>
                        @endif
                        @if ($task->notes)
                            <span class="text-gray-600">{{ $task->notes }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($venueGroup['matches']->isNotEmpty())
        <ul class="divide-y divide-gray-100">
            @foreach ($venueGroup['matches'] as $match)
                <li class="grid gap-x-4 gap-y-1 px-4 py-3 md:grid-cols-[3.5rem_minmax(11rem,1.7fr)_4.5rem_minmax(15rem,1.1fr)] md:items-baseline md:px-5">
                    <span class="font-bold text-gray-900">{{ DateFormat::time($match->time) ?? '-' }}</span>

                    <p class="min-w-0 leading-snug">
                        <span class="font-semibold text-gray-900">{{ $match->home_team_name }}</span>
                        <span class="text-gray-400">-</span>
                        <span class="text-gray-900">{{ $match->away_team_name }}</span>
                        <span class="whitespace-nowrap text-xs text-gray-400">@if ($match->pool){{ $match->pool }} · @endif{{ $match->match_code }}</span>
                    </p>

                    <span class="text-sm text-gray-600">
                        @if ($match->secondCourt?->name)
                            <span title="CenterCourt">CC</span>
                        @elseif ($match->court?->name)
                            {{ __('Veld :court', ['court' => $match->court->name]) }}
                        @endif
                    </span>

                    {{-- Referee and scorer stay whole blocks: otherwise "niet
                         ingedeeld" breaks in the middle and the line frays. --}}
                    @if ($showDuties)
                        <span class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 text-sm">
                            <span class="whitespace-nowrap">
                                <span class="text-gray-500">{{ __('Fluiten') }}</span>
                                <span class="{{ filled($match->referee) ? 'font-medium text-gray-900' : 'text-amber-700' }}">{{ $match->referee ?: __('niet ingedeeld') }}</span>
                            </span>
                            <span class="whitespace-nowrap">
                                <span class="text-gray-500">{{ __('Tellen') }}</span>
                                <span class="{{ filled($match->scorer) ? 'font-medium text-gray-900' : 'text-amber-700' }}">{{ $match->scorer ?: __('niet ingedeeld') }}</span>
                            </span>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
