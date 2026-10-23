{{-- The "Rasterbal": a 2×2 calendar grid with one cell a volleyball, plus the wordmark. Colours come from design-system.css. --}}
<span class="vp-logo">
    <svg viewBox="0 0 48 48" width="26" height="26" aria-hidden="true" class="vp-logo-mark">
        <rect x="2" y="2" width="20" height="20" rx="5" class="vp-logo-cell"></rect>
        <g transform="translate(36 12)" fill="none" stroke-width="3.2" stroke-linecap="round" class="vp-logo-ball">
            <circle r="8.4"></circle>
            <path d="M0 0 Q3.98 -3.34 0 -8.4"></path>
            <path d="M0 0 Q0.9 5.12 7.27 4.2"></path>
            <path d="M0 0 Q-4.89 -1.78 -7.27 4.2"></path>
        </g>
        <rect x="2" y="26" width="20" height="20" rx="5" class="vp-logo-cell"></rect>
        <rect x="26" y="26" width="20" height="20" rx="5" class="vp-logo-cell"></rect>
    </svg>
    <span class="vp-logo-word">Volleybalplanner</span>
    @if (filament()->getId() === 'platform')
        <span class="vp-logo-tag">{{ __('Platform') }}</span>
    @endif
</span>
