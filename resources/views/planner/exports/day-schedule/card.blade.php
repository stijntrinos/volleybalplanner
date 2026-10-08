@php
    $rooms = $lockerRooms[$match->id] ?? ['home' => null, 'away' => null];

    $teams = $match->home_team_name . ' – ' . $match->away_team_name;
    $lockerRoomLine = __('Kleedkamer') . ' ' . ($rooms['home'] ?: '-') . ' · ' . __('Kleedkamer') . ' ' . ($rooms['away'] ?: '-');
    $dutyLine = __('Fluiten') . ' ' . ($match->referee ?: ($match->nevobo_referee ? 'Nevobo' : '-')) . ' · ' . __('Tellen') . ' ' . ($match->scorer ?: '-');
@endphp

<div class="card" style="grid-column: {{ $column }} / span {{ $columnSpan }}; grid-row: {{ $row }};">
    {{-- The kick-off time heads the card; the teams and duties sit centred below. --}}
    <div class="card__time"><span class="card__time-tab">{{ $time }}</span></div>
    <div class="card__body">
        {{-- Every line shrinks as far as needed to stay on one line; a second
             line would make the whole row of the schedule taller. --}}
        <div class="card__teams" style="font-size: {{ $textSize($teams, $columnSpan, 18) }}px;">{{ $teams }}</div>
        @if ($showLockerRooms && $match->venue?->show_locker_rooms_on_schedule)
            <div class="card__locker-rooms" style="font-size: {{ $textSize($lockerRoomLine, $columnSpan, 17) }}px;">
                {{ $lockerRoomLine }}
            </div>
        @endif
        @if ($showDuties)
            <div class="card__duties" style="font-size: {{ $textSize($dutyLine, $columnSpan, 15) }}px;">
                {{ $dutyLine }}
            </div>
        @endif
    </div>
</div>
