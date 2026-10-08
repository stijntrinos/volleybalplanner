@php
    use Filament\Enums\UserMenuPosition;
@endphp
<div class="vp-topbar-ctn">
    <div class="vp-topbar">
        {{-- Below the desktop breakpoint the sidebar is off-canvas; Filament's own open button lives in the topbar we replaced. --}}
        <button
            type="button"
            class="vp-topbar-menu"
            x-data
            x-on:click="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
            aria-label="{{ __('Menu openen') }}"
        >
            <x-filament::icon icon="heroicon-o-bars-3" class="vp-topbar-menu-icon" />
        </button>

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
            <x-filament::dropdown placement="bottom-end" width="xs" teleport>
                <x-slot name="trigger">
                    <button
                        type="button"
                        class="vp-topbar-bell"
                        aria-label="{{ trans_choice(':count punt dat aandacht vraagt|:count punten die aandacht vragen', $attention) }}"
                        title="{{ __('Wat aandacht vraagt') }}"
                    >
                        <x-filament::icon icon="heroicon-o-bell" class="vp-topbar-bell-icon" />
                        <span class="vp-topbar-bell-count">{{ $attention }}</span>
                    </button>
                </x-slot>

                <x-filament::dropdown.header>{{ __('Vraagt aandacht') }}</x-filament::dropdown.header>
                <x-filament::dropdown.list>
                    @foreach ($attentionItems as $item)
                        <x-filament::dropdown.list.item tag="a" :href="$item['url']" :badge="$item['count']" badge-color="warning">
                            {{ $item['label'] }}
                        </x-filament::dropdown.list.item>
                    @endforeach
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        @endif

        @if (filament()->auth()->check() && filament()->hasUserMenu())
            <x-filament-panels::user-menu :position="UserMenuPosition::Topbar" />
        @endif
    </div>
</div>
