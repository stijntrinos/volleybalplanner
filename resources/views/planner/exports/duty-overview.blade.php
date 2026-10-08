{{--
    The duty overview as it is shared as an image. The same colours and font
    as the day schedule, so the two read as one house style.
--}}
<!doctype html>
<html lang="nl">

    <head>
        <meta charset="utf-8">
        <title>{{ __('Takenoverzicht') }}</title>
        <style>
            @font-face {
                font-family: 'Outfit';
                font-weight: 400;
                src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['regular'] }}') format('truetype');
            }

            @font-face {
                font-family: 'Outfit';
                font-weight: 700;
                src: url('data:font/truetype;charset=utf-8;base64,{{ $fonts['bold'] }}') format('truetype');
            }

            :root {
                --planner-primary: {{ $branding['primary_color'] }};
                --primary-dark: color-mix(in srgb, var(--planner-primary) 62%, #0b1220);
                --background: color-mix(in srgb, var(--planner-primary) 22%, #fff);
                --column-head: color-mix(in srgb, var(--planner-primary) 80%, #fff);
                --grey: #3c3c3c;
                --border: #e1e5eb;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                background: var(--background);
                font-family: 'Outfit', sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            .sheet {
                width: {{ $width }}px;
                padding: 32px;
                background: var(--background);
            }

            .header {
                display: flex;
                align-items: center;
                gap: 24px;
                padding: 20px 28px;
                border-radius: 14px;
                background: var(--primary-dark);
                color: #fff;
            }

            .header img { height: 72px; }
            .header h1 { margin: 0; font-size: 40px; letter-spacing: .02em; text-transform: uppercase; }
            .header p { margin: 4px 0 0; font-size: 22px; opacity: .85; }
            .header__club-name { font-size: 22px; font-weight: 700; text-transform: uppercase; opacity: .85; }

            table {
                width: 100%;
                margin-top: 20px;
                border-collapse: separate;
                border-spacing: 0;
                overflow: hidden;
                border-radius: 14px;
                background: #fff;
                color: var(--grey);
                font-size: 20px;
            }

            th {
                padding: 14px 12px;
                background: var(--column-head);
                color: #fff;
                font-size: 16px;
                letter-spacing: .06em;
                text-align: center;
                text-transform: uppercase;
            }

            th:first-child, td:first-child { text-align: left; padding-left: 22px; }
            td { padding: 11px 12px; border-top: 1px solid var(--border); text-align: center; }
            tbody tr:nth-child(even) td { background: #f4f7fb; }
            td.team { font-weight: 700; color: var(--primary-dark); }
            td.total { font-weight: 700; font-size: 22px; }
            .short { color: #b45309; font-weight: 700; }
            .above { color: #15803d; font-weight: 700; }
            tfoot td { background: #eef2f7; font-weight: 700; border-top: 2px solid var(--column-head); }

            .footer {
                margin-top: 14px;
                color: var(--primary-dark);
                font-size: 16px;
                text-align: right;
            }
        </style>
    </head>

    <body>
        <div class="sheet">
            <div class="header">
                @if ($branding['logo'])
                    <img src="{{ $branding['logo'] }}" alt="{{ $branding['club_name'] }}">
                @endif
                <div>
                    @unless ($branding['logo'])
                        <div class="header__club-name">{{ $branding['club_name'] }}</div>
                    @endunless
                    <h1>{{ __('Takenoverzicht') }}</h1>
                    <p>{{ $period }}</p>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>{{ __('Team') }}</th>
                        <th>{{ __('Fluiten') }}</th>
                        <th>{{ __('Tellen') }}</th>
                        <th>{{ __('Zaaldienst') }}</th>
                        @if ($showVolleystars)
                            <th>{{ __('VolleyStars') }}</th>
                        @endif
                        <th>{{ __('Totaal') }}</th>
                        @if ($showPoints)
                            <th>{{ __('Stand') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td class="team">{{ $row['team'] }}</td>
                            <td>{{ $row['referee'] ?? 0 }}</td>
                            <td>{{ $row['scorer'] ?? 0 }}</td>
                            <td>{{ $row['hall_duty'] ?? 0 }}</td>
                            @if ($showVolleystars)
                                <td>{{ $row['volleystars'] ?? 0 }}</td>
                            @endif
                            <td class="total">{{ $row['total'] ?? 0 }}</td>
                            @if ($showPoints)
                                @php $percentage = (float) ($row['percentage'] ?? 0); @endphp
                                <td @class(['short' => $percentage > 0, 'above' => $percentage < 0])>{{ $row['status_label'] ?? '' }}</td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('Alle teams') }}</td>
                        <td>{{ $totals['referee'] ?? 0 }}</td>
                        <td>{{ $totals['scorer'] ?? 0 }}</td>
                        <td>{{ $totals['hall_duty'] ?? 0 }}</td>
                        @if ($showVolleystars)
                            <td>{{ $totals['volleystars'] ?? 0 }}</td>
                        @endif
                        <td class="total">{{ $totals['total'] ?? 0 }}</td>
                        @if ($showPoints)
                            <td></td>
                        @endif
                    </tr>
                </tfoot>
            </table>

            <p class="footer">
                @if ($showPoints)
                    {{ __('Taakpunten volgens de vrijwilligerspunten uit de plannerinstellingen') }}
                @else
                    {{ __('Aantal diensten per team') }}
                @endif
            </p>
        </div>
    </body>

</html>
