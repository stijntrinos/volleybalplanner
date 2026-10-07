@php use App\Planner\Support\DateFormat; @endphp

<div class="mx-auto max-w-7xl px-4 pb-20 md:py-4">
    <header class="mt-6">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ __('Wedstrijden & diensten') }}</h1>
        <p class="mt-1 text-gray-600">
            {{ $showDuties ? __('Alle thuiswedstrijden en diensten vanaf vandaag.') : __('Alle thuiswedstrijden vanaf vandaag.') }}
        </p>
    </header>

    <section class="mt-6 border border-gray-200 bg-gray-50 p-4 sm:p-6" aria-label="{{ __('Wedstrijden filteren') }}">
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_13rem_13rem_auto] lg:items-end">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-gray-800" for="match-search">{{ __('Zoeken') }}</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-gray-400" aria-hidden="true"
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <path d="m20 20-3.5-3.5" />
                    </svg>
                    <input class="w-full border border-gray-300 bg-white py-2.5 pl-10 pr-3 text-gray-900"
                        id="match-search" type="search" maxlength="100" autocomplete="off"
                        wire:model.live.debounce.300ms="search"
                        placeholder="{{ __('Team, tegenstander of sporthal') }}">
                </div>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-gray-800" for="match-team">{{ __('Team') }}</label>
                <select class="w-full border border-gray-300 bg-white py-2.5 pl-3 pr-8 text-gray-900"
                    id="match-team" wire:model.live="team">
                    <option value="">{{ __('Alle teams') }}</option>
                    @foreach ($teams as $teamName)
                        <option value="{{ $teamName }}">{{ $teamName }}</option>
                    @endforeach
                </select>
            </div>

            @if ($venues->count() > 1)
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-gray-800" for="match-venue">{{ __('Sporthal filteren') }}</label>
                    <select class="w-full border border-gray-300 bg-white py-2.5 pl-3 pr-8 text-gray-900"
                        id="match-venue" wire:model.live="venue">
                        <option value="">{{ __('Alle sporthallen') }}</option>
                        @foreach ($venues as $venueOption)
                            <option value="{{ $venueOption->id }}">{{ $venueOption->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if ($hasFilter)
                <button class="border border-gray-300 bg-white px-4 py-2.5 font-semibold text-gray-700 transition hover:bg-gray-100"
                    type="button" wire:click="clearFilters">{{ __('Wissen') }}</button>
            @endif
        </div>
    </section>

    <div class="mt-7 flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4">
        <p class="text-sm text-gray-600" aria-live="polite">
            <strong class="font-semibold text-gray-900">{{ $matchCount }}</strong>
            {{ $matchCount === 1 ? __('wedstrijd') : __('wedstrijden') }}
            @if ($showDuties)
                <span aria-hidden="true"> · </span>
                <strong class="font-semibold text-gray-900">{{ $hallDutyCount }}</strong>
                {{ $hallDutyCount === 1 ? __('zaaldienst') : __('zaaldiensten') }}
            @endif
            @if ($showVolleystars)
                <span aria-hidden="true"> · </span>
                <strong class="font-semibold text-gray-900">{{ $volleystarsCount }}</strong>
                {{ $volleystarsCount === 1 ? __('VolleyStars-dienst') : __('VolleyStars-diensten') }}
            @endif
            @if ($hasFilter)
                <span class="text-gray-500">{{ __('gevonden in het hele seizoen') }}</span>
            @endif
        </p>
        @if ($version?->published_at)
            <p class="text-xs text-gray-500">{{ __('Planning gepubliceerd op :date', ['date' => DateFormat::dateTime($version->published_at)]) }}</p>
        @endif
    </div>

    @if ($months->isNotEmpty())
        <nav class="-mx-4 mt-5 overflow-x-auto px-4" aria-label="{{ __('Maand kiezen') }}">
            <ul class="flex w-max gap-2">
                @foreach ($months as $monthChoice)
                    @php $isCurrent = $monthChoice['value'] === $month; @endphp
                    <li>
                        <button
                            class="flex items-baseline gap-1.5 border px-3.5 py-2 text-sm font-semibold transition {{ $isCurrent ? 'planner-bg-primary planner-border-primary' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-100' }}"
                            type="button" wire:click="$set('month', '{{ $monthChoice['value'] }}')"
                            @if ($isCurrent) aria-current="true" @endif>
                            {{ $monthChoice['label'] }}
                            <span class="text-xs font-normal {{ $isCurrent ? 'opacity-80' : 'text-gray-500' }}">{{ $monthChoice['count'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </nav>
    @endif

    @forelse ($days as $day)
        <section class="mt-8" aria-labelledby="day-{{ $day['date'] }}">
            <div class="planner-border-primary flex flex-wrap items-baseline justify-between gap-2 border-b-2 pb-2">
                <h2 class="text-xl font-bold text-gray-900" id="day-{{ $day['date'] }}">
                    {{ DateFormat::withWeekday($day['date']) }}
                </h2>
                <span class="text-sm text-gray-500">
                    {{ $day['match_count'] }} {{ $day['match_count'] === 1 ? __('wedstrijd') : __('wedstrijden') }}
                </span>
            </div>

            @foreach ($day['venues'] as $venueGroup)
                @include('planner::livewire.partials.match-overview-venue', ['venueGroup' => $venueGroup, 'showDuties' => $showDuties])
            @endforeach
        </section>
    @empty
        <section class="mt-8 border border-dashed border-gray-300 bg-gray-50 px-6 py-14 text-center">
            <svg class="mx-auto size-10 text-gray-400" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M8 2v3m8-3v3M3.5 9h17M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" />
            </svg>
            <h2 class="mt-3 text-lg font-semibold text-gray-900">{{ __('Geen wedstrijden gevonden') }}</h2>
            <p class="mx-auto mt-1 max-w-lg text-gray-600">
                @if (! $version)
                    {{ __('Er is nog geen wedstrijdplanning gepubliceerd.') }}
                @elseif ($hasFilter)
                    {{ __('Pas de zoekterm of de filters aan om meer van de planning te zien.') }}
                @else
                    {{ __('Er staan vanaf vandaag geen thuiswedstrijden meer in de planning.') }}
                @endif
            </p>
        </section>
    @endforelse
</div>
