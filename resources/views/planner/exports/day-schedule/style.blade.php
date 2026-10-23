<style>
    @font-face {
        font-family: 'Outfit';
        font-weight: 400;
        font-style: normal;
        src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['regular'] }}') format('truetype');
    }

    @font-face {
        font-family: 'Outfit';
        font-weight: 700;
        font-style: normal;
        src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['bold'] }}') format('truetype');
    }

    /*
     * The Volleybalplanner day schedule (design system "Export · Dagschema"):
     * the club colour carries the header and footer bars and the court
     * headings; the sheet itself stays in the planner's neutral greys, so a
     * printed schedule reads the same for every club.
     */
    :root {
        --planner-primary: {{ $branding['primary_color'] }};
        --ink: #15232c;
        --ink-muted: #526672;
        --ink-faint: #7d909a;
        --surface: #f3f6f7;
        --raised: #ffffff;
        --line: #d6dfe3;
        --empty: #e7edf0;
        --radius: 10px;
    }

    * {
        box-sizing: border-box;
    }

    /*
     * The frame is the size of the image itself. What the sheet leaves around
     * it keeps the surface colour, so the edge blends with the sheet.
     */
    body {
        margin: 0;
        width: {{ $targetWidth }}px;
        height: {{ $targetHeight }}px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--surface);
        font-family: 'Outfit', 'Segoe UI', sans-serif;
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
    }

    /*
     * The sheet keeps its own sizes; the script at the bottom of the page
     * stretches the width and scales the whole into the frame.
     */
    .sheet {
        flex: 0 0 auto;
        width: {{ $width }}px;
        background: var(--surface);
    }

    .column-header__icon svg {
        display: block;
        width: 100%;
        height: auto;
    }

    .column-header__icon svg path,
    .column-header__icon svg g {
        fill: currentColor;
    }

    /* ---------- Header ---------- */
    .header {
        padding: 22px 32px;
        background: var(--planner-primary);
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .header__logo,
    .header__club-name {
        flex: none;
        width: 76px;
        height: 76px;
        border-radius: 12px;
        background: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .header__logo img {
        max-width: 64px;
        max-height: 64px;
        object-fit: contain;
    }

    .header__club-name {
        padding: 6px;
        color: var(--planner-primary);
        font-size: 13px;
        font-weight: 700;
        line-height: 1.1;
        text-align: center;
        word-break: break-word;
    }

    .header__club-name--spacer {
        visibility: hidden;
    }

    .header__titles {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 2px;
        text-align: center;
    }

    .header__venue {
        margin: 0;
        font-size: 34px;
        line-height: 1.15;
        font-weight: 700;
        letter-spacing: .02em;
    }

    .header__date {
        margin: 0;
        font-size: 22px;
        font-weight: 400;
        opacity: .92;
    }

    .header__tagline {
        margin: 4px 0 0;
        font-size: 15px;
        opacity: .85;
    }

    /* ---------- Grid ---------- */
    .grid {
        display: grid;
        grid-template-columns: repeat({{ $grid->columns() }}, minmax(0, 1fr)) @if ($showDuties) {{ $hallDutyWidth }}px @endif;
        /* The headings keep their own height; only the time slots share the minimum. */
        grid-template-rows: auto;
        grid-auto-rows: minmax({{ $minimumRowHeight }}px, auto);
        gap: {{ $rowGap }}px {{ $gap }}px;
        padding: 18px {{ $margin }}px 18px;
    }

    .column-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: {{ $header['gap'] }}px;
        padding: 8px {{ $header['padding'] }}px;
        min-height: 48px;
        border-radius: var(--radius);
        background: var(--planner-primary);
        color: #ffffff;
    }

    .column-header__number {
        display: none;
    }

    .column-header__name {
        font-weight: 700;
        white-space: nowrap;
        overflow: hidden;
        line-height: 1.1;
    }

    .column-header--hall-duty {
        gap: {{ $dutyHeader['gap'] }}px;
        padding: 8px {{ $dutyHeader['padding'] }}px;
        background: var(--ink);
    }

    .column-header__icon {
        flex: none;
        width: {{ $dutyHeader['icon'] }}px;
        color: #ffffff;
    }

    /* ---------- Match cards ---------- */
    .card {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--line);
        border-radius: var(--radius);
        background: var(--raised);
        overflow: hidden;
    }

    .card__time {
        min-height: {{ $barHeight }}px;
        display: flex;
        align-items: center;
        padding: 5px 12px 4px;
        background: var(--surface);
        border-bottom: 1px solid var(--line);
    }

    .card__time-tab {
        font-size: 15px;
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        color: var(--ink);
    }

    .card__body {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        padding: 8px 12px 10px;
        text-align: center;
    }

    .card__teams {
        max-width: 100%;
        font-weight: 700;
        line-height: 1.2;
        white-space: nowrap;
        color: var(--ink);
    }

    .card__locker-rooms,
    .card__duties {
        max-width: 100%;
        line-height: 1.2;
        white-space: nowrap;
        color: var(--ink-muted);
    }

    /* ---------- Hall duty ---------- */
    .hall-duty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        padding: 10px;
        border-radius: var(--radius);
        background: var(--ink);
        color: #ffffff;
        text-align: center;
    }

    .hall-duty__period {
        font-size: 14px;
        opacity: .9;
        font-variant-numeric: tabular-nums;
    }

    .hall-duty__team {
        font-size: 24px;
        font-weight: 700;
    }

    .empty {
        border-radius: var(--radius);
        background: var(--empty);
    }

    /* ---------- Footer ---------- */
    .footer {
        padding: 12px 32px;
        background: var(--planner-primary);
        color: #ffffff;
        display: flex;
        align-items: center;
        gap: 24px;
    }

    .footer__text {
        flex: 1;
        text-align: center;
        font-size: 17px;
        font-weight: 700;
        letter-spacing: .04em;
    }

    .footer__credit {
        flex: none;
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
        opacity: .85;
    }

    .footer__credit strong {
        font-weight: 700;
    }

    .schedule-footer {
        padding: 10px {{ $margin }}px;
        background: var(--raised);
    }
</style>
