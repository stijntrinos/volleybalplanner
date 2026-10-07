@php
    $visibleVenues = collect($day['venues'] ?? [])->reject(fn ($venue) => in_array($venue['id'], $hiddenVenues));
    $showHallDuty = $day['show_hall_duty'] ?? false;
    $sideBySide = fn (array $layout): string => $layout['columns'] > 1
        ? sprintf('left: calc(5px + %1$s * (100%% - 10px) / %2$s); width: calc((100%% - 10px) / %2$s - 4px); right: auto;', $layout['column'], $layout['columns'])
        : '';
@endphp
<div class="planner-scroll">
    <div class="planner-grid" style="--planner-columns: {{ max(1, $visibleVenues->sum(fn ($venue) => count($venue['courts']) + ($showHallDuty ? 1 : 0))) }}">
        <div class="planner-time-header"></div>
        <div class="planner-headings">
            @foreach ($day['venues'] ?? [] as $venueIndex => $venue)
                @continue(in_array($venue['id'], $hiddenVenues))
                @foreach ($venue['courts'] as $court)
                    <div class="planner-heading planner-heading-{{ $venueIndex % 4 }}"><strong>{{ $venue['name'] }}@if ($venue['rental_window'] ?? null)<em class="planner-rental" title="{{ __('Inhuur van deze zaal op deze dag') }}">{{ $venue['rental_window'] }}</em>@endif</strong><span>{{ __('Veld :court', ['court' => $court['name']]) }}</span></div>
                @endforeach
                @if ($showHallDuty)
                    <div class="planner-heading planner-heading-{{ $venueIndex % 4 }} planner-service-heading"><strong>{{ $venue['name'] }}</strong><span>{{ __('Zaaldienst') }}</span></div>
                @endif
            @endforeach
        </div>

        <div class="planner-time-axis">
            @for ($hour = 8; $hour <= 23; $hour++)
                <span style="top: {{ $timetable::top(sprintf('%02d:00', $hour)) }}px">{{ sprintf('%02d:00', $hour) }}</span>
            @endfor
        </div>
        <div class="planner-fields">
            @foreach ($day['venues'] ?? [] as $venue)
                @continue(in_array($venue['id'], $hiddenVenues))
                @foreach ($venue['courts'] as $courtIndex => $court)
                    <div class="planner-dropzone" data-venue-id="{{ $venue['id'] }}" data-court-id="{{ $court['id'] }}">
                        @if ($courtIndex === 0)
                            @foreach (collect($day['volleystars'] ?? [])->where('venue_id', $venue['id']) as $block)
                                @php
                                    $blockTeams = collect($block['tasks'] ?? [])->pluck('team.code')->filter()->unique();
                                @endphp
                                <button type="button" class="planner-card planner-volleystars-card is-hall-wide"
                                    wire:click="openVolleystars({{ $block['id'] }})" title="{{ __('Taken en teams direct koppelen') }}"
                                    style="top: {{ $timetable::top($block['setup_start']) }}px; height: {{ $timetable::heightBetween($block['setup_start'], $block['teardown_end']) }}px; width: calc({{ count($venue['courts']) }} * 100% - 10px)">
                                    <strong>VolleyStars</strong>
                                    <small>{{ $blockTeams->isNotEmpty() ? $blockTeams->join(', ') : __('Teams nog toewijzen') }}</small>
                                </button>
                            @endforeach
                        @endif
                        @foreach ($this->visibleMatches as $match)
                            {{-- A match on two courts is drawn once, in its first court. --}}
                            @continue($match['court_id'] != $court['id'])
                            @php
                                $spansTwoCourts = filled($match['second_court_id']);
                                $layout = $courtLayout[(int) $court['id']][(int) $match['id']] ?? ['column' => 0, 'columns' => 1];
                                $overlapping = $layout['columns'] > 1;
                            @endphp
                            <article class="planner-match planner-card {{ $editable ? '' : 'is-readonly' }} {{ $spansTwoCourts ? 'spans-two-fields' : '' }} {{ $match['change_status'] ? 'is-requested' : '' }} {{ $overlapping ? 'is-overlapping' : '' }} {{ ($match['has_change_request'] ?? false) ? 'has-request' : '' }}"
                                data-match-id="{{ $match['id'] }}"
                                role="button"
                                tabindex="0"
                                aria-label="{{ __('Wedstrijd :code om :time: :home tegen :away', ['code' => $match['code'], 'time' => $match['time'], 'home' => $match['home_team'], 'away' => $match['away_team']]) }}"
                                wire:click="openMatch({{ $match['id'] }})"
                                wire:keydown.enter="openMatch({{ $match['id'] }})"
                                wire:keydown.space.prevent="openMatch({{ $match['id'] }})"
                                style="top: {{ $timetable::top($match['time'] ?: '08:00') }}px; height: {{ $timetable::height($match['duration_minutes']) }}px; {{ $sideBySide($layout) }}">
                                <div><strong>{{ $match['time'] }}</strong> <span>#{{ $match['code'] }}</span></div>
                                <b>{{ $match['home_team'] }}</b>
                                <small>{{ $match['away_team'] }}</small>
                                <div class="planner-card-meta">
                                    @if ($match['provisional'] ?? null)<span class="planner-provisional-badge" title="{{ __('Voorgesteld, wacht op akkoord. Tot dan blijft de wedstrijd hier staan.') }}">{{ __('Naar :place', ['place' => $match['provisional']]) }}</span>@endif
                                    @if ($overlapping)<span class="planner-overlap-badge" title="{{ __('Twee wedstrijden staan op dit veld op hetzelfde moment') }}">{{ __('Dubbel geboekt') }}</span>@endif
                                    @if ($match['has_change_request'] ?? false)<span class="planner-request-badge" title="{{ __('Een team heeft gevraagd deze wedstrijd te verzetten') }}">{{ __('Verzoek') }}</span>@endif
                                    @if ($match['referee'])<span>{{ __('F: :team', ['team' => $match['referee']]) }}</span>@endif
                                    @if ($match['scorer'])<span>{{ __('T: :team', ['team' => $match['scorer']]) }}</span>@endif
                                    @if ($editable)<button type="button" wire:click.stop="openAssistant({{ $match['id'] }})" title="{{ __('Verplaatsingsassistent') }}">{{ __('Voorstel') }}</button>@endif
                                    @if ($editable && $match['change_requested'])
                                        <button type="button" wire:click.stop="markNevoboApplied({{ $match['id'] }})" wire:confirm="{{ __('Is deze wijziging in Nevobo doorgevoerd?') }}">{{ __('Nevobo klaar') }}</button>
                                        <button type="button" wire:click.stop="cancelChangeRequest({{ $match['id'] }})" wire:confirm="{{ ($match['provisional'] ?? null) ? __('Het voorstel afwijzen? De wedstrijd blijft hier staan.') : __('Wijzigingsverzoek annuleren en de oorspronkelijke planning herstellen?') }}">{{ __('Annuleren') }}</button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                        @foreach ($day['provisional'] ?? [] as $proposal)
                            @continue($proposal['court_id'] != $court['id'])
                            @php
                                $layout = $courtLayout[(int) $court['id']][(int) $proposal['id']] ?? ['column' => 0, 'columns' => 1];
                            @endphp
                            {{-- A proposal stands here until it is confirmed; the match itself
                                 is still on its own day, and on the website too. --}}
                            <article class="planner-match planner-card is-provisional {{ $editable ? '' : 'is-readonly' }} {{ filled($proposal['second_court_id']) ? 'spans-two-fields' : '' }}"
                                data-match-id="{{ $proposal['id'] }}"
                                aria-label="{{ __('Voorstel: wedstrijd :code om :time, staat nog op :place', ['code' => $proposal['code'], 'time' => $proposal['time'], 'place' => $proposal['currently_at']]) }}"
                                style="top: {{ $timetable::top($proposal['time'] ?: '08:00') }}px; height: {{ $timetable::height($proposal['duration_minutes']) }}px; {{ $sideBySide($layout) }}">
                                <div><strong>{{ $proposal['time'] }}</strong> <span>#{{ $proposal['code'] }}</span></div>
                                <b>{{ $proposal['home_team'] }}</b>
                                <small>{{ $proposal['away_team'] }}</small>
                                <div class="planner-card-meta">
                                    <span class="planner-provisional-badge" title="{{ __('Wacht op akkoord. Tot dan staat de wedstrijd nog op zijn eigen plek.') }}">{{ __('Voorstel · staat op :place', ['place' => $proposal['currently_at']]) }}</span>
                                    @if ($editable)
                                        <button type="button" wire:click.stop="markNevoboApplied({{ $proposal['id'] }})" wire:confirm="{{ __('Is de verplaatsing bevestigd en in Nevobo verwerkt? De wedstrijd gaat dan naar deze plek.') }}">{{ __('Bevestigd') }}</button>
                                        <button type="button" wire:click.stop="cancelChangeRequest({{ $proposal['id'] }})" wire:confirm="{{ __('Het voorstel afwijzen? De wedstrijd blijft op zijn eigen plek staan.') }}">{{ __('Afwijzen') }}</button>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                        @foreach (collect($day['activities'] ?? [])->where('court_id', $court['id']) as $activity)
                            <article class="planner-card planner-activity-card" style="top: {{ $timetable::top($activity['start_time']) }}px; height: {{ $timetable::heightFrom($activity['start_time'], $activity['end_time']) }}px"><strong>{{ $activity['title'] }}</strong><small>{{ substr($activity['start_time'], 0, 5) }}@if ($activity['end_time'])–{{ substr($activity['end_time'], 0, 5) }}@endif</small></article>
                        @endforeach
                        @foreach (collect($day['trainings'] ?? [])->where('venue_id', $venue['id'])->filter(fn ($training) => (string) $training['court'] === (string) $court['name']) as $training)
                            <article class="planner-card planner-training-card" style="top: {{ $timetable::top($training['start_time']) }}px; height: {{ $timetable::heightBetween($training['start_time'], $training['end_time']) }}px"><strong>{{ $training['team']['code'] ?? __('Training') }}</strong><small>{{ substr($training['start_time'], 0, 5) }}–{{ substr($training['end_time'], 0, 5) }}</small></article>
                        @endforeach
                    </div>
                @endforeach
                @if ($showHallDuty)
                    <div class="planner-dropzone planner-service-column">
                        @foreach (collect($day['hall_duties'] ?? [])->where('venue_id', $venue['id']) as $duty)
                            <article class="planner-card planner-service-card" style="top: {{ $timetable::top($duty['start_time']) }}px; height: {{ $timetable::heightBetween($duty['start_time'], $duty['end_time']) }}px">
                                <div><strong>{{ $duty['team']['code'] ?? $duty['team']['name'] ?? __('Zaaldienst') }}</strong></div>
                                <small>{{ substr($duty['start_time'], 0, 5) }}–{{ substr($duty['end_time'], 0, 5) }}</small>
                            </article>
                        @endforeach
                    </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
