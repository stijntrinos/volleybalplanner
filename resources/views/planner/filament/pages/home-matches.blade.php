<x-filament-panels::page>
    @php
        // Written out in full: Blade compiles this view as a block inside the
        // page component, where no use statement is allowed.
        $timetable = \App\Planner\Support\Timetable::class;
        $dateFormat = \App\Planner\Support\DateFormat::class;
        $courtLayout = $this->courtLayout;

        $editable = $day['editable'] ?? false;
    @endphp
    <div
        x-data="{ searchOpen: false }"
        @keydown.window.escape="searchOpen = false"
        @keydown.window.prevent.cmd.k="searchOpen = true; $nextTick(() => $refs.plannerSearch?.focus())"
        @keydown.window.prevent.ctrl.k="searchOpen = true; $nextTick(() => $refs.plannerSearch?.focus())"
        @planner-search-result-selected.window="searchOpen = false"
        data-planner
        data-planner-view="{{ $versionId }}:{{ $date }}"
        data-planner-moved-label="{{ __('Wedstrijd verplaatst') }}"
        data-planner-undo-label="{{ __('Ongedaan maken') }}"
        {!! $timetable::attributes() !!}
        class="planner-shell"
    >
        @include('planner::filament.pages.home-matches.toolbar')

        @if (! $versionId)
            <x-filament::section :heading="__('Nog geen wedstrijdplanning')" :description="__('Maak een conceptversie aan; de Nevobo-wedstrijden worden daarna elke nacht opgehaald.')">
                @if ($this->versionsUrl)
                    <x-filament::button :href="$this->versionsUrl" tag="a" icon="heroicon-o-square-3-stack-3d">
                        {{ __('Naar planningversies') }}
                    </x-filament::button>
                @endif
            </x-filament::section>
        @elseif (empty($day['matches']) && empty($day['to_plan']))
            <x-filament::section :heading="__('Geen wedstrijden op deze dag')" :description="__('Gebruik de pijlen om naar een wedstrijddag te gaan.')" />
        @endif

        @if ($versionId && ! $editable && ! \App\Support\Tenancy\ReadOnlyClub::active())
            <div class="planner-readonly-notice">{{ __('Deze planning is gepubliceerd en daarom alleen-lezen. Maak of selecteer een conceptversie om wedstrijden te verplaatsen.') }}</div>
        @endif

        @if ($day['calendar'] ?? [])
            <div class="planner-chips">
                @foreach ($day['calendar'] as $event)
                    @php $color = \App\Planner\Support\PrimaryColor::from($event['type_color'] ?? '#6B7280'); @endphp
                    <span style="background-color: {{ $color }}; border-color: {{ $color }}; color: white;">
                        {{ $event['type'] }} · {{ $event['name'] }}
                    </span>
                @endforeach
            </div>
        @endif

        <div class="planner-hall-filter">
            @foreach ($day['venues'] ?? [] as $venue)
                @php $hidden = in_array($venue['id'], $hiddenVenues); @endphp
                <button
                    type="button"
                    wire:click="toggleVenue({{ $venue['id'] }})"
                    aria-pressed="{{ $hidden ? 'false' : 'true' }}"
                    aria-label="{{ $venue['name'] }} ({{ __(':count wedstrijden', ['count' => $venue['matches'] ?? 0]) }}) {{ $hidden ? __('tonen') : __('verbergen') }}"
                    @class(['is-hidden' => $hidden])
                >{{ $venue['name'] }}<em class="planner-hall-count" title="{{ __('Wedstrijden in deze zaal op deze dag') }}">{{ $venue['matches'] ?? 0 }}</em></button>
            @endforeach
        </div>

        <div class="planner-layout">
            @if ($showUnplanned)
                @include('planner::filament.pages.home-matches.unplanned')
            @endif

            @include('planner::filament.pages.home-matches.grid')
        </div>

        @include('planner::filament.pages.home-matches.match-modal')
        @include('planner::filament.pages.home-matches.volleystars-modal')
        @include('planner::filament.pages.home-matches.confirmation')
        @include('planner::filament.pages.home-matches.assistant')
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
