@php use App\Planner\Services\VenueOccupancyService; use App\Planner\Support\DateFormat; @endphp
<!doctype html><html lang="nl"><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#1f2937}h1{font-size:18px;margin-bottom:2px}h2{font-size:13px;margin:16px 0 4px}
p.meta{margin:0 0 4px;color:#6b7280}table{width:100%;border-collapse:collapse;margin-bottom:6px}
th{background:{{ $primaryColor }};color:#fff;text-align:left}th,td{padding:5px 6px;border:1px solid #dbe3ea}
td.num{text-align:right}
.status{font-weight:bold}
.available{color:#15803d}.partial{color:#b45309}.conflict{color:#b91c1c}.closed{color:#6b7280}.unknown{color:#b91c1c}
tr.detail td{background:#f8fafc;color:#475569;font-size:8px}
</style></head><body>

<h1>{{ __('Sporthalbezetting & beschikbaarheid') }}</h1>
<p class="meta">
    {{ __('Periode') }} {{ DateFormat::date($overview['from']) }}
    {{ __('t/m') }} {{ DateFormat::date($overview['to']) }}
    · {{ $overview['total'] }} {{ __('regels') }}
    @if ($overview['conflicts'] > 0) · {{ $overview['conflicts'] }} {{ __('met conflict') }} @endif
</p>
<p class="meta">{{ __('Gegenereerd') }} {{ now()->format('d-m-Y H:i') }}</p>

@foreach ($overview['venues'] as $venue)
    <h2>{{ $venue['venue'] }} ({{ count($venue['rows']) }} {{ __('datums') }})</h2>
    <table>
        <thead>
            <tr>
                <th>{{ __('Datum') }}</th><th>{{ __('Dag') }}</th><th>{{ __('Beschikbaar vanaf') }}</th><th>{{ __('Beschikbaar tot') }}</th>
                <th>{{ __('Wedstrijden') }}</th><th>{{ __('Eerste') }}</th><th>{{ __('Laatste') }}</th><th>{{ __('Einde laatste') }}</th><th>{{ __('Status') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($venue['rows'] as $row)
                <tr>
                    <td>{{ DateFormat::date($row['date']) }}</td>
                    <td>{{ $row['day'] }}</td>
                    <td>{{ $row['available_from'] ?? '—' }}</td>
                    <td>{{ $row['available_until'] ?? '—' }}</td>
                    <td class="num">{{ $row['matches'] }}</td>
                    <td>{{ $row['first'] ?? '—' }}</td>
                    <td>{{ $row['last'] ?? '—' }}</td>
                    <td>{{ $row['end_of_last'] ?? '—' }}</td>
                    <td class="status {{ $row['status'] }}">{{ VenueOccupancyService::statusLabel($row['status']) }}</td>
                </tr>

                @if ($withDetails)
                    @foreach ($row['details'] as $detail)
                        <tr class="detail">
                            <td></td>
                            <td>{{ $detail['code'] }}</td>
                            <td colspan="2">{{ $detail['home'] }} – {{ $detail['away'] }}</td>
                            <td class="num">{{ $detail['court'] ?? '—' }}</td>
                            <td>{{ $detail['start'] ?? '—' }}</td>
                            <td>{{ $detail['end'] ?? '—' }}</td>
                            <td colspan="2">{{ $detail['duration'] }}</td>
                        </tr>
                    @endforeach
                @endif
            @endforeach
        </tbody>
    </table>
@endforeach

@if ($overview['venues'] === [])
    <p>{{ __('Geen regels gevonden voor de gekozen filters.') }}</p>
@endif

</body></html>
