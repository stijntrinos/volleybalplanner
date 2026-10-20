<x-filament-panels::page>
    <div class="vp-stats">
        <div class="vp-stats-toolbar">
            <p class="vp-muted">{{ __('Bezoek aan de openbare website, vergeleken met de :days dagen ervoor.', ['days' => $days]) }}</p>
            <div class="vp-segmented" role="group" aria-label="{{ __('Periode') }}">
                @foreach ([7, 30, 90] as $option)
                    <button type="button" wire:click="$set('period', '{{ $option }}')" aria-pressed="{{ $days === $option ? 'true' : 'false' }}"
                            @class(['is-active' => $days === $option])>{{ __(':days dagen', ['days' => $option]) }}</button>
                @endforeach
            </div>
        </div>

        <div class="vp-stats-kpis">
            @foreach ($kpis as $kpi)
                <div class="vp-card vp-stats-kpi">
                    <span class="vp-overline">{{ $kpi['label'] }}</span>
                    <span class="vp-stats-kpi-value">{{ $kpi['value'] }}</span>
                    @if ($kpi['change'] === null)
                        <span class="vp-stats-change is-neutral">{{ __('Geen vergelijking') }}</span>
                    @else
                        @php($isBetter = $kpi['change'] == 0 ? null : ($kpi['change'] > 0) === $kpi['higherIsBetter'])
                        <span @class(['vp-stats-change', 'is-better' => $isBetter === true, 'is-worse' => $isBetter === false, 'is-neutral' => $isBetter === null])>
                            {{ $kpi['change'] > 0 ? '↑' : ($kpi['change'] < 0 ? '↓' : '→') }}
                            {{ number_format(abs($kpi['change']) * 100, 0, ',', '.') }}%
                            <span class="vp-muted">{{ __('t.o.v. vorige periode') }}</span>
                        </span>
                    @endif
                    <span class="vp-stats-hint">{{ $kpi['hint'] }}</span>
                </div>
            @endforeach
        </div>

        <section class="vp-card vp-stats-panel">
            <header class="vp-stats-panel-head">
                <h2>{{ __('Bezoeken per dag') }}</h2>
                <span class="vp-muted">{{ $timeline->first()['date']->translatedFormat('j M') }} – {{ __('vandaag') }}</span>
            </header>

            @if ($timeline->sum('views') === 0)
                <div class="vp-stats-empty">
                    <x-filament::icon icon="heroicon-o-chart-bar" class="vp-stats-empty-icon" />
                    <strong>{{ __('Nog geen bezoek in deze periode') }}</strong>
                    <span class="vp-muted">{{ __('Zodra bezoekers statistieken toestaan, verschijnen de metingen hier.') }}</span>
                </div>
            @else
                <div class="vp-chart" style="--vp-chart-days: {{ $days }}">
                    <div class="vp-chart-grid" aria-hidden="true">
                        @foreach ([$scaleMax, $scaleMax / 2, 0] as $tick)
                            <div class="vp-chart-gridline"><span>{{ number_format($tick, 0, ',', '.') }}</span></div>
                        @endforeach
                    </div>
                    <div class="vp-chart-bars" role="list">
                        @foreach ($timeline as $index => $day)
                            @php($summary = $day['date']->translatedFormat('D j M').': '.trans_choice(':count bezoek|:count bezoeken', $day['visits']).', '.trans_choice(':count paginaweergave|:count paginaweergaven', $day['views']))
                            <div class="vp-chart-day" role="listitem" tabindex="0" aria-label="{{ $summary }}">
                                <span @class(['vp-chart-bar', 'is-empty' => $day['visits'] === 0]) style="height: {{ round($day['visits'] / $scaleMax * 100, 2) }}%"></span>
                                <span class="vp-chart-tip" aria-hidden="true">
                                    <strong>{{ $day['date']->translatedFormat('D j M') }}</strong>
                                    <span>{{ trans_choice(':count bezoek|:count bezoeken', $day['visits']) }}</span>
                                    <span class="vp-muted">{{ trans_choice(':count paginaweergave|:count paginaweergaven', $day['views']) }}</span>
                                </span>
                                @if (($days - 1 - $index) % $labelEvery === 0)
                                    <span @class(['vp-chart-label', 'is-minor' => intdiv($days - 1 - $index, $labelEvery) % 2 === 1]) aria-hidden="true">{{ $index === $days - 1 ? __('vandaag') : $day['date']->translatedFormat($days === 7 ? 'D' : 'j M') }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        <div class="vp-stats-lists">
            @foreach ($lists as $list)
                <section class="vp-card vp-stats-panel">
                    <header class="vp-stats-panel-head">
                        <h2>{{ $list['heading'] }}</h2>
                        <span class="vp-muted">{{ __('weergaven') }}</span>
                    </header>
                    @forelse ($list['rows'] as $row)
                        <div class="vp-rank">
                            <span class="vp-rank-fill" style="width: calc((100% - 3.2em - 10px) * {{ round($row['views'] / $list['rows']->max('views'), 4) }})" aria-hidden="true"></span>
                            <span class="vp-rank-label" title="{{ $row['label'] }}">
                                @if ($list['column'] === 'device_type')
                                    <x-filament::icon :icon="match ($row['label']) { 'mobile' => 'heroicon-o-device-phone-mobile', 'tablet' => 'heroicon-o-device-tablet', default => 'heroicon-o-computer-desktop' }" class="vp-rank-icon" />
                                    {{ match ($row['label']) { 'mobile' => __('Mobiel'), 'tablet' => __('Tablet'), default => __('Desktop') } }}
                                @elseif ($list['column'] === 'referrer_host')
                                    {{ $row['label'] ?? __('Direct of onbekend') }}
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </span>
                            <span class="vp-rank-value">{{ number_format($row['views'], 0, ',', '.') }}</span>
                            <span class="vp-rank-share">{{ number_format($row['share'] * 100, 0, ',', '.') }}%</span>
                        </div>
                    @empty
                        <p class="vp-muted vp-stats-list-empty">{{ __('Nog geen metingen in deze periode.') }}</p>
                    @endforelse
                </section>
            @endforeach
        </div>

        <p class="vp-stats-footnote">
            {{ __('Alleen bezoek met toestemming wordt gemeten; bots en platformbeheerders tellen niet mee. Een bezoek is een browsersessie per dag, geen telling van personen. De besloten planner valt buiten deze statistieken.') }}
        </p>
    </div>
</x-filament-panels::page>
