@php
    $club = \Filament\Facades\Filament::getTenant();
    $isAdmin = \App\Filament\Club\Pages\Subscription::canAccess();
    $readOnly = $club instanceof \App\Models\Club && $club->isReadOnly();
    // The read-only notice is for everyone; the "ends soon" warning only for club admins.
    $notice = $club instanceof \App\Models\Club && ($readOnly || $isAdmin) ? $club->subscriptionNotice() : null;
    // Grey over the planning, except where the subscription and the own account are handled.
    $tint = $readOnly && ! request()->routeIs(\App\Filament\Club\Pages\Subscription::getRouteName(), '*.account');
@endphp
@if ($notice)
    <div @class(['vp-banner', 'vp-subscription-banner', 'is-danger' => $notice['tone'] === 'danger'])>
        <x-filament::icon :icon="$readOnly ? 'heroicon-o-lock-closed' : 'heroicon-o-calendar-days'" class="vp-banner-icon" />
        <p class="vp-banner-text">
            <strong>{{ $notice['message'] }}</strong>
            @if ($readOnly)
                {{ __('De planning staat in de leesstand: je kunt alles bekijken, maar niets wijzigen.') }}
                {{ $isAdmin ? __('Vraag de verlenging aan om weer verder te kunnen.') : __('Vraag een clubbeheerder om het abonnement te verlengen.') }}
            @else
                {{ __('Vraag op tijd de verlenging aan.') }}
            @endif
        </p>
        @if ($isAdmin)
            <button type="button" class="vp-banner-link" x-data x-on:click="$dispatch('planner-upgrade', { renew: true })">{{ __('Verlenging aanvragen') }}</button>
        @endif
    </div>
@endif
@if ($tint)
    <div class="vp-read-only-tint" aria-hidden="true"></div>
@endif
