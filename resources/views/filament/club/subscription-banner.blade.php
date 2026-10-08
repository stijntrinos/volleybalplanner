@php
    $club = \Filament\Facades\Filament::getTenant();
    $notice = $club instanceof \App\Models\Club && \App\Filament\Club\Pages\Subscription::canAccess() ? $club->subscriptionNotice() : null;
@endphp
@if ($notice)
    <div @class(['vp-banner', 'vp-subscription-banner', 'is-danger' => $notice['tone'] === 'danger'])>
        <x-filament::icon icon="heroicon-o-calendar-days" class="vp-banner-icon" />
        <p class="vp-banner-text"><strong>{{ $notice['message'] }}</strong> {{ __('Neem contact op om het abonnement te verlengen.') }}</p>
        <a href="{{ \App\Filament\Club\Pages\Subscription::getUrl() }}" class="vp-banner-link">{{ __('Naar abonnement') }}</a>
    </div>
@endif
