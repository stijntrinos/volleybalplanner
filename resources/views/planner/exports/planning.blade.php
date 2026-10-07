<!doctype html><html lang="nl"><head><meta charset="utf-8"><style>
    body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#1f2937}h1{font-size:18px;margin:0 0 4px}p{margin:0 0 16px;color:#64748b}table{width:100%;border-collapse:collapse}th{background:{{ $primaryColor }};color:white;text-align:left}th,td{padding:5px;border:1px solid #dbe3ea}tr:nth-child(even){background:#f4f7fa}.changed{color:#b91c1c;font-weight:bold}
</style></head><body>
<h1>{{ __('Wedstrijdplanning') }} — {{ $version->name }}</h1><p>{{ __('Gegenereerd op') }} {{ now()->format('d-m-Y H:i') }}</p>
<table><thead><tr><th>{{ __('Datum') }}</th><th>{{ __('Tijd') }}</th><th>{{ __('Sporthal') }}</th><th>{{ __('Veld') }}</th><th>{{ __('Thuis') }}</th><th>{{ __('Uit') }}</th><th>{{ __('Poule') }}</th><th>{{ __('Code') }}</th>@if ($showDuties)<th>{{ __('Fluiten') }}</th><th>{{ __('Tellen') }}</th>@endif</tr></thead><tbody>
@foreach ($matches as $match)<tr><td>{{ $match->date?->format('d-m-Y') }}</td><td>{{ substr((string) $match->time, 0, 5) }}</td><td>{{ $match->venue?->name }}</td><td>{{ $match->court?->name }}</td><td>{{ $match->home_team_name }}</td><td>{{ $match->away_team_name }}</td><td>{{ $match->pool }}</td><td>{{ $match->match_code }}</td>@if ($showDuties)<td>{{ $match->referee }}</td><td>{{ $match->scorer }}</td>@endif</tr>@endforeach
</tbody></table></body></html>
