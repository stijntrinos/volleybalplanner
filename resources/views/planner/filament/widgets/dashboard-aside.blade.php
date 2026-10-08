<x-filament-widgets::widget class="vp-widget-bare">
    <div class="vp-aside">
        <section class="vp-card vp-aside-card">
            <div class="vp-aside-head">
                <h2>{{ __('Nevobo-sync') }}</h2>
                @if ($sync['at'])
                    <span class="vp-muted">{{ $sync['at'] }}</span>
                @endif
            </div>
            @foreach ($sync['rows'] as $row)
                <div class="vp-sync-row">
                    <span class="vp-sync-count is-{{ $row['tone'] }}">{{ $row['count'] }}</span>
                    <span class="vp-sync-label">{{ $row['label'] }}</span>
                    @if ($row['url'] && $row['action'])
                        <a href="{{ $row['url'] }}" class="vp-link">{{ $row['action'] }}</a>
                    @endif
                </div>
            @endforeach
        </section>

        @if ($requestsUrl !== null || count($requests))
            <section class="vp-card vp-aside-card">
                <div class="vp-aside-head">
                    <h2>{{ __('Nieuwe verzoeken') }}</h2>
                    @if ($requestsUrl)
                        <a href="{{ $requestsUrl }}" class="vp-link">{{ __('Alle verzoeken') }}</a>
                    @endif
                </div>
                @forelse ($requests as $request)
                    <article class="vp-request" wire:key="dashboard-request-{{ $request['id'] }}">
                        <div class="vp-request-meta"><span>{{ $request['kind'] }}</span><span>{{ $request['when'] }}</span></div>
                        <div class="vp-request-title">{{ $request['title'] }}</div>
                        <div class="vp-request-reason">{{ $request['reason'] }}</div>
                        @if ($request['canDecide'])
                            <div class="vp-request-actions">
                                <x-filament::button size="sm" wire:click="decide({{ $request['id'] }}, 'accepted')" wire:loading.attr="disabled">{{ __('Accepteren') }}</x-filament::button>
                                <x-filament::button size="sm" color="gray" wire:click="decide({{ $request['id'] }}, 'rejected')" wire:loading.attr="disabled">{{ __('Afwijzen') }}</x-filament::button>
                                <button type="button" class="vp-text-button" wire:click="decide({{ $request['id'] }}, 'parked')" wire:loading.attr="disabled">{{ __('Parkeren') }}</button>
                            </div>
                        @endif
                    </article>
                @empty
                    <p class="vp-muted vp-empty">{{ __('Geen openstaande verzoeken.') }}</p>
                @endforelse
            </section>
        @endif
    </div>
</x-filament-widgets::widget>
