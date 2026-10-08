<x-filament-widgets::widget class="vp-widget-bare">
    <section class="vp-card vp-days">
        <h2>{{ __('Komende wedstrijddagen') }}</h2>
        @if (count($days))
            <div class="vp-days-grid">
                @foreach ($days as $day)
                    <{{ $day['url'] ? 'a' : 'div' }} @if ($day['url']) href="{{ $day['url'] }}" @endif class="vp-day">
                        <span class="vp-day-date">{{ $day['day'] }}</span>
                        <span class="vp-day-where">{{ $day['where'] ?: __('Nog geen sporthal') }}</span>
                        <span @class(['vp-stat-note', 'is-warn' => $day['warn']])>{{ $day['note'] }}</span>
                    </{{ $day['url'] ? 'a' : 'div' }}>
                @endforeach
            </div>
        @else
            <p class="vp-muted">{{ __('Er staan geen thuiswedstrijden meer gepland.') }}</p>
        @endif
    </section>
</x-filament-widgets::widget>
