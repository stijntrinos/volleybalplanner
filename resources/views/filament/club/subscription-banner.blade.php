@php
    $club = \Filament\Facades\Filament::getTenant();
    $isAdmin = \App\Filament\Club\Pages\Subscription::canAccess();
    $readOnly = $club instanceof \App\Models\Club && $club->isReadOnly();
    // The read-only notice is for everyone; the "ends soon" warning only for club admins.
    $notice = $club instanceof \App\Models\Club && ($readOnly || $isAdmin) ? $club->subscriptionNotice() : null;
@endphp
@if ($notice)
    <div @class(['vp-banner', 'vp-subscription-banner', 'is-danger' => $notice['tone'] === 'danger'])>
        <x-filament::icon :icon="$readOnly ? 'heroicon-o-lock-closed' : 'heroicon-o-calendar-days'" class="vp-banner-icon" />
        <p class="vp-banner-text">
            <strong>{{ $notice['message'] }}</strong>
            @if ($readOnly)
                {{ __('De planning staat in de leesstand: je kunt alles bekijken, maar niets wijzigen.') }}
                {{ $isAdmin ? __('Neem contact op om het abonnement te verlengen.') : __('Vraag een clubbeheerder om het abonnement te verlengen.') }}
            @else
                {{ __('Neem contact op om het abonnement te verlengen.') }}
            @endif
        </p>
        @if ($isAdmin)
            <a href="{{ \App\Filament\Club\Pages\Subscription::getUrl() }}" class="vp-banner-link">{{ __('Naar abonnement') }}</a>
        @endif
    </div>
@endif
