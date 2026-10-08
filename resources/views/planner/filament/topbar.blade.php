@php
    use Filament\Enums\UserMenuPosition;
@endphp
<div class="vp-topbar-ctn">
    <div class="vp-topbar">
        @if ($searchUrl)
            {{--
                On the day planner the search modal opens in place; elsewhere the
                button leads to the day planner, which opens it on arrival.
            --}}
            <button
                type="button"
                class="vp-topbar-search"
                x-data
                x-on:click="document.querySelector('[data-planner]') ? $dispatch('planner-open-search') : (window.location.href = @js($searchUrl))"
                aria-label="{{ __('Zoek team of wedstrijdcode') }}"
                title="{{ __('Wedstrijd zoeken (⌘K / Ctrl+K)') }}"
            >
                <x-filament::icon icon="heroicon-o-magnifying-glass" class="vp-topbar-search-icon" />
                <span class="vp-topbar-search-label">{{ __('Zoek team of wedstrijdcode') }}</span>
                <kbd>Ctrl K</kbd>
            </button>
        @endif

        <div class="vp-topbar-spacer"></div>

        @if ($activeVersion)
            <span class="vp-topbar-status is-active" title="{{ __('Deze planning staat op de website en in de feeds') }}">{{ __('Actief: :version', ['version' => $activeVersion]) }}</span>
        @elseif ($searchUrl)
            <span class="vp-topbar-status is-muted">{{ __('Nog geen actieve planning') }}</span>
        @endif

        @if ($attention > 0)
            <a
                @if ($attentionUrl) href="{{ $attentionUrl }}" @endif
                class="vp-topbar-bell"
                aria-label="{{ trans_choice(':count punt dat aandacht vraagt|:count punten die aandacht vragen', $attention) }}"
                title="{{ __('Nieuwe verzoeken en Nevobo-wijzigingen') }}"
            >
                <x-filament::icon icon="heroicon-o-bell" class="vp-topbar-bell-icon" />
                <span class="vp-topbar-bell-count">{{ $attention }}</span>
            </a>
        @endif

        @if (filament()->auth()->check() && filament()->hasUserMenu())
            <x-filament-panels::user-menu :position="UserMenuPosition::Topbar" />
        @endif
    </div>
</div>
