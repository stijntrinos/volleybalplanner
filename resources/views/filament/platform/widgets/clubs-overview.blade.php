<x-filament-widgets::widget class="vp-widget-bare">
    @php($clubs = $this->getClubs())

    @if ($clubs->isEmpty())
        <div class="vp-card vp-empty-card">
            Er zijn nog geen clubs. Maak er een aan via <strong>Clubs</strong>.
        </div>
    @else
        <div class="vp-club-grid">
            @foreach ($clubs as $club)
                <a href="{{ $club['url'] }}" target="_blank" rel="noopener" @class(['vp-card', 'vp-club', 'is-inactive' => ! $club['active']])
                   title="Open {{ $club['name'] }} in een nieuw tabblad">
                    <div class="vp-club-head">
                        <img src="{{ $club['avatar'] }}" alt="" class="vp-club-avatar">
                        <div class="vp-club-name">
                            <strong>{{ $club['name'] }}</strong>
                            <span>/{{ $club['slug'] }}</span>
                        </div>
                        <span @class(['vp-badge', 'is-ok' => $club['active'], 'is-muted' => ! $club['active']])>
                            {{ $club['active'] ? 'Actief' : 'Inactief' }}
                        </span>
                    </div>
                    <div class="vp-club-meta">
                        <span>{{ $club['users'] }} {{ $club['users'] === 1 ? 'gebruiker' : 'gebruikers' }}</span>
                        <span>{{ $club['modules'] }} {{ $club['modules'] === 1 ? 'module' : 'modules' }}</span>
                        <span>sinds {{ $club['created'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
