{{--
    The day schedule as it leaves as an image.

    CSS Grid does the layout: DayScheduleGrid only supplies column and row
    numbers, with a span for a match on two courts or a hall duty over several
    time slots.

    The sheet sits in a frame of {{ $targetWidth }}x{{ $targetHeight }}; the
    script at the bottom stretches and scales it into that frame.
--}}
<!doctype html>
<html lang="nl">

    <head>
        <meta charset="utf-8">
        <title>{{ __('Dagschema') }} {{ $date }}</title>
        @include('planner::exports.day-schedule.style')
    </head>

    <body>
        <div class="sheet">
            <div class="header">
                @if ($branding['logo'])
                    <div class="header__logo"><img src="{{ $branding['logo'] }}" alt="{{ $branding['club_name'] }}"></div>
                @else
                    <div class="header__club-name">{{ $branding['club_name'] }}</div>
                @endif
                <div class="header__titles">
                    <h1 class="header__venue">{{ $venueName }}</h1>
                    <p class="header__date">{{ $date }}</p>
                    @if ($branding['tagline'])
                        <p class="header__tagline">{{ $branding['tagline'] }}</p>
                    @endif
                </div>
                @if ($branding['logo'])
                    <div class="header__logo"><img src="{{ $branding['logo'] }}" alt="{{ $branding['club_name'] }}"></div>
                @else
                    <div class="header__club-name header__club-name--spacer" aria-hidden="true"></div>
                @endif
            </div>

            <div class="grid">
                @foreach ($grid->courts() as $index => $court)
                    <div class="column-header" style="grid-column: {{ $index + 1 }};">
                        <span class="column-header__number">{{ $court->name }}</span>
                        <span class="column-header__name" style="font-size: {{ min(24, $courtHeaderSize($court->name)) }}px;">
                            {{ __('Veld') }} {{ $court->name }}
                        </span>
                    </div>
                @endforeach

                @if ($showDuties)
                    <div class="column-header column-header--hall-duty" style="grid-column: {{ $grid->hallDutyColumn() }};">
                        <span class="column-header__icon">{!! $svg('hall-duty') !!}</span>
                        <span class="column-header__name" style="font-size: {{ min(22, $dutyHeaderSize) }}px;">{{ __('Zaalwacht') }}</span>
                    </div>
                @endif

                @foreach ($grid->emptyBlocks() as $block)
                    <div class="empty"
                        style="grid-column: {{ $block['column'] }} / span {{ $block['column_span'] }}; grid-row: {{ $block['row'] }};">
                    </div>
                @endforeach

                @foreach ($grid->cards() as $card)
                    @include('planner::exports.day-schedule.card', [
                        'match' => $card['match'],
                        'column' => $card['column'],
                        'columnSpan' => $card['column_span'],
                        'row' => $card['row'],
                        'time' => \App\Planner\Support\DateFormat::time($card['match']->time),
                    ])
                @endforeach

                @if ($showDuties)
                    @foreach ($grid->hallDuties() as $block)
                        <div class="hall-duty"
                            style="grid-column: {{ $grid->hallDutyColumn() }}; grid-row: {{ $block['row'] }} / span {{ $block['row_span'] }};">
                            <div class="hall-duty__period">{{ $block['start'] }} – {{ $block['end'] }}</div>
                            <div class="hall-duty__team">{{ $block['duty']->team?->code ?: '-' }}</div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="footer">
                <div class="footer__text">{{ $footerText }}</div>
                <div class="footer__credit">
                    <span>{{ __('Gepland met') }}</span>
                    <svg viewBox="0 0 48 48" width="14" height="14" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" fill="#FFFFFF"></rect><g transform="translate(36 12)" fill="none" stroke="#FFFFFF" stroke-width="3.8" stroke-linecap="round"><circle r="8.1"></circle><path d="M0 0 Q3.98 -3.34 0 -8.1"></path><path d="M0 0 Q0.9 5.12 7.01 4.05"></path><path d="M0 0 Q-4.89 -1.78 -7.01 4.05"></path></g><rect x="2" y="26" width="20" height="20" rx="5" fill="#FFFFFF"></rect><rect x="26" y="26" width="20" height="20" rx="5" fill="#FFFFFF"></rect></svg>
                    <strong>Volleybalplanner</strong>
                </div>
            </div>

            @if (filled($scheduleFooter))
                <div class="schedule-footer">{!! $scheduleFooter !!}</div>
            @endif
        </div>

        {{--
            Fit the sheet into the frame. A day schedule is taller than wide, so
            the height determines the scale and width is left over; that is why
            the sheet first stretches to the ratio of the frame, up to the limit
            from the service. Widening is allowed, narrowing is not: the font
            sizes in the cards were calculated in PHP on the narrowest width and
            would then overflow their card.
        --}}
        <script>
            (async () => {
                const sheet = document.querySelector('.sheet');

                try {
                    await document.fonts.ready;

                    const ratio = {{ $targetWidth }} / {{ $targetHeight }};
                    const stretch = Math.min({{ $maxWidth }}, Math.round(sheet.offsetHeight * ratio));

                    if (stretch > sheet.offsetWidth) {
                        sheet.style.width = stretch + 'px';
                    }

                    const scale = Math.min(
                        {{ $targetWidth }} / sheet.offsetWidth,
                        {{ $targetHeight }} / sheet.offsetHeight,
                    );

                    sheet.style.transform = 'scale(' + scale + ')';
                } finally {
                    // Even when measuring fails an image should come out; the
                    // sheet then sits unscaled in the frame.
                    window.dayScheduleScaled = true;
                }
            })();
        </script>
    </body>

</html>
