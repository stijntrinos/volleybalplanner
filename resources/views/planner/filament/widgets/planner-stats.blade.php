<x-filament-widgets::widget class="vp-widget-bare">
    <div class="vp-stats">
        @foreach ($stats as $stat)
            <div class="vp-card vp-stat">
                <span class="vp-stat-label">{{ $stat['label'] }}</span>
                <span class="vp-stat-value">{{ $stat['value'] }}</span>
                <span @class(['vp-stat-note', 'is-warn' => $stat['warn']])>{{ $stat['note'] }}</span>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
