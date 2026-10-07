<style>
    @font-face {
        font-family: 'Inria Sans';
        font-weight: 400;
        font-style: normal;
        src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['regular'] }}') format('truetype');
    }

    @font-face {
        font-family: 'Inria Sans';
        font-weight: 700;
        font-style: normal;
        src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['bold'] }}') format('truetype');
    }

    /*
     * Every colour derives from the club colour (--planner-primary), so the
     * schedule follows the club's identity without a stylesheet of its own.
     */
    :root {
        --planner-primary: {{ $branding['primary_color'] }};
        --primary-dark: color-mix(in srgb, var(--planner-primary) 62%, #0b1220);
        --primary-soft: color-mix(in srgb, var(--planner-primary) 55%, #fff);
        --background: color-mix(in srgb, var(--planner-primary) 22%, #fff);
        --column-head: color-mix(in srgb, var(--planner-primary) 80%, #fff);
        --accent: #f59e0b;
        --accent-deep: #d97706;
        --team: var(--primary-dark);
        --grey: #3c3c3c;
        --border: #e1e5eb;
        --empty: color-mix(in srgb, var(--planner-primary) 32%, #fff);
        --radius: 11px;
        /* The light rim that lifts the blocks off the background. */
        --ring: 0 0 0 3px rgba(255, 255, 255, .88), 0 0 8px 3px rgba(255, 255, 255, .4);
    }

    * {
        box-sizing: border-box;
    }

    /*
     * The frame is the size of the image itself. What the sheet leaves around
     * it keeps the background colour, so the edge blends with the sheet
     * instead of standing out as an empty strip.
     */
    body {
        margin: 0;
        width: {{ $targetWidth }}px;
        height: {{ $targetHeight }}px;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--background);
        font-family: 'Inria Sans', sans-serif;
        color: #fff;
        -webkit-font-smoothing: antialiased;
    }

    /*
     * The sheet keeps its own sizes; the script at the bottom of the page
     * stretches the width and scales the whole into the frame. Without
     * flex-shrink flex would squash the sheet first and only then scale.
     */
    .sheet {
        flex: 0 0 auto;
        width: {{ $width }}px;
        background: var(--background);
    }

    /*
     * The icons are filled paths with their own width and height. They must
     * scale with their box, and the colour comes from the parent.
     */
    .header__watermark svg,
    .footer__watermark svg,
    .hall-duty__watermark svg,
    .column-header__icon svg {
        display: block;
        width: 100%;
        height: auto;
    }

    .header__watermark svg path,
    .header__watermark svg g,
    .footer__watermark svg path,
    .footer__watermark svg g,
    .hall-duty__watermark svg path,
    .hall-duty__watermark svg g,
    .column-header__icon svg path,
    .column-header__icon svg g {
        fill: currentColor;
    }

    /* ---------- Header ---------- */
    .header {
        position: relative;
        overflow: hidden;
        padding: 18px 26px 16px;
        background: var(--primary-dark);
        display: flex;
        align-items: center;
        gap: 22px;
    }

    .header__watermark {
        position: absolute;
        color: var(--primary-soft);
        opacity: .3;
    }

    .header__watermark--net {
        left: 152px;
        top: -6px;
        width: 240px;
    }

    .header__watermark--ball {
        right: 158px;
        top: -26px;
        width: 196px;
    }

    .header__logo {
        position: relative;
        flex: 0 0 auto;
        width: 96px;
        height: 96px;
        padding: 7px;
        border-radius: 9px;
        background: #fff;
    }

    .header__logo img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    .header__titles {
        position: relative;
        flex: 1 1 auto;
        text-align: center;
    }

    .header__venue {
        margin: 0;
        font-size: 42px;
        font-weight: 700;
        line-height: 1.04;
        letter-spacing: .5px;
        color: #fdf6ec;
        text-transform: uppercase;
    }

    .header__date {
        margin: 2px 0 0;
        font-size: 34px;
        font-weight: 700;
        line-height: 1.1;
        color: var(--accent);
    }

    .header__tagline {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 14px;
        margin-top: 10px;
        font-size: 12px;
        font-weight: 400;
        letter-spacing: 4.5px;
        color: rgba(255, 255, 255, .72);
        text-transform: uppercase;
    }

    .header__tagline::before,
    .header__tagline::after {
        content: '';
        width: 62px;
        height: 1px;
        background: rgba(255, 255, 255, .35);
    }

    /* ---------- Grid ---------- */
    .grid {
        display: grid;
        grid-template-columns: repeat({{ $grid->columns() }}, minmax(0, 1fr)) @if ($showDuties) {{ $hallDutyWidth }}px @endif;
        grid-template-rows: max-content;
        grid-auto-rows: minmax({{ $minimumRowHeight }}px, auto);
        gap: {{ $rowGap }}px {{ $gap }}px;
        padding: 16px {{ $margin }}px 20px;
    }

    .column-header {
        grid-row: 1;
        display: flex;
        align-items: center;
        gap: {{ $header['gap'] }}px;
        padding: 4px {{ $header['padding'] }}px;
        border-radius: var(--radius);
        background: var(--column-head);
    }

    /*
     * A circle for a court number, a pill for a name like "1A": at a fixed
     * width anything longer than one character ran out of the circle.
     */
    .column-header__number {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: {{ $header['number'] }}px;
        height: {{ $header['number'] }}px;
        padding: 0 12px;
        border-radius: {{ (int) ($header['number'] / 2) }}px;
        background: var(--primary-dark);
        font-size: 31px;
        font-weight: 700;
        color: #fff;
    }

    .column-header__number,
    .column-header__name {
        position: relative;
        top: -2px;
    }

    /*
     * The size is set per header in the markup, chosen so the label fits.
     * Clipping should therefore no longer happen.
     */
    .column-header__name {
        flex: 1 1 auto;
        font-weight: 700;
        letter-spacing: .4px;
        text-align: center;
        text-transform: uppercase;
        white-space: nowrap;
    }

    /* The shield has the dark tone, like the number circle. */
    .column-header__icon {
        flex: 0 0 auto;
        width: {{ $dutyHeader['icon'] }}px;
        color: var(--primary-dark);
    }

    .column-header--hall-duty {
        gap: {{ $dutyHeader['gap'] }}px;
        padding: 4px {{ $dutyHeader['padding'] }}px;
    }

    .column-header--hall-duty .column-header__name {
        letter-spacing: .2px;
    }

    /* ---------- Match card ---------- */
    .card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border-radius: var(--radius);
        background: #fff;
        box-shadow: var(--ring);
    }

    /* The bar over the full width; the tab with the time drops out in the middle. */
    .card__time {
        position: relative;
        height: {{ $barHeight }}px;
        border-radius: var(--radius) var(--radius) 0 0;
        background: var(--primary-dark);
    }

    .card__time-tab {
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        padding: 3px 24px 4px;
        border-radius: 0 0 var(--radius) var(--radius);
        background: var(--primary-dark);
        font-size: 19px;
        font-weight: 700;
        line-height: 1.16;
        white-space: nowrap;
        color: #fff;
    }

    /*
     * The hollow shoulders where the tab leaves the bar. border-radius cannot
     * make an inward curve; a radial gradient can: transparent inside the
     * radius, the primary tone outside.
     */
    .card__time-tab::before,
    .card__time-tab::after {
        content: '';
        position: absolute;
        top: {{ $barHeight }}px;
        width: 7px;
        height: 7px;
    }

    .card__time-tab::before {
        left: -7px;
        background: radial-gradient(circle at 0 100%, transparent 7px, var(--primary-dark) 7px);
    }

    .card__time-tab::after {
        right: -7px;
        background: radial-gradient(circle at 100% 100%, transparent 7px, var(--primary-dark) 7px);
    }

    .card__body {
        flex: 1 1 auto;
        white-space: nowrap;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
        gap: 2px;
        /* Top space keeps the first line clear of the dropping tab. */
        padding: 18px 10px 8px;
        text-align: center;
    }

    .card__teams {
        font-weight: 700;
        line-height: 1.2;
        color: var(--team);
    }

    .card__locker-rooms {
        font-weight: 700;
        line-height: 1.2;
        color: var(--accent-deep);
    }

    .card__duties {
        line-height: 1.2;
        color: var(--grey);
    }

    /* ---------- Hall duty ---------- */
    .hall-duty {
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        border-radius: var(--radius);
        background: var(--primary-dark);
        box-shadow: var(--ring);
    }

    .hall-duty__watermark {
        position: absolute;
        top: 50%;
        right: -18px;
        width: 96px;
        transform: translateY(-50%);
        color: var(--primary-soft);
        opacity: .3;
    }

    .hall-duty__period {
        position: relative;
        font-size: 23px;
        font-weight: 700;
        color: #fff;
    }

    .hall-duty__team {
        position: relative;
        font-size: 33px;
        font-weight: 700;
        line-height: 1.1;
        color: var(--accent);
    }

    .empty {
        border-radius: var(--radius);
        background: var(--empty);
    }

    /* ---------- Footer ---------- */
    .footer {
        position: relative;
        overflow: hidden;
        padding: 13px 24px 14px;
        background: var(--primary-dark);
        text-align: center;
    }

    .footer__watermark {
        position: absolute;
        top: -8px;
        width: 74px;
        color: var(--primary-soft);
        opacity: .34;
    }

    .footer__watermark--left {
        left: 148px;
    }

    .footer__watermark--right {
        right: 148px;
    }

    /* The lines next to the text run to the edge, with the balls over them. */
    .footer__text {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 26px;
        font-size: 28px;
        font-weight: 700;
        letter-spacing: .4px;
        color: var(--accent);
        text-transform: uppercase;
    }

    .footer__text::before,
    .footer__text::after {
        content: '';
        flex: 1 1 auto;
        height: 2px;
        background: rgba(255, 255, 255, .26);
    }

    /* Whatever PlannerPlugin::scheduleFooterUsing() renders, e.g. a sponsor row. */
    .schedule-footer {
        background: #fff;
    }

    /* Without a logo the club name takes its place, in a box of the same size. */
    .header__club-name {
        position: relative;
        flex: 0 0 auto;
        width: 160px;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.1;
        color: #fff;
        text-transform: uppercase;
    }

    .header__club-name--spacer {
        visibility: hidden;
    }
</style>
