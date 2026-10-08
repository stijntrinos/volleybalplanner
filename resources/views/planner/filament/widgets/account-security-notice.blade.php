<x-filament-widgets::widget class="vp-widget-bare">
    <div class="vp-notice is-warn">
        <x-filament::icon icon="heroicon-o-shield-exclamation" class="vp-notice-icon" />
        <div class="vp-notice-text">
            <strong>{{ __('Beveilig je account') }}</strong>
            <span>{{ __('Je logt nu alleen in met een wachtwoord. Zet tweestapsverificatie aan of voeg een passkey toe; de planner bevat contactgegevens van leden.') }}</span>
        </div>
        @if ($url)
            <a href="{{ $url }}" class="vp-notice-action">{{ __('Naar Mijn account') }}</a>
        @endif
    </div>
</x-filament-widgets::widget>
