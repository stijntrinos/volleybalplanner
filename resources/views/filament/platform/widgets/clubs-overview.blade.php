<x-filament-widgets::widget>
    @php($clubs = $this->getClubs())

    @if ($clubs->isEmpty())
        <x-filament::section>
            Er zijn nog geen clubs. Maak er een aan via <strong>Clubs</strong>.
        </x-filament::section>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem;">
            @foreach ($clubs as $club)
                <a href="{{ $club['url'] }}" target="_blank" rel="noopener" style="display: block; text-decoration: none; color: inherit;"
                   title="Open {{ $club['name'] }} in een nieuw tabblad">
                    <x-filament::section style="height: 100%; {{ $club['active'] ? '' : 'opacity: .6;' }}">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem;">
                            <div style="min-width: 0;">
                                <div style="font-weight: 600; font-size: 1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $club['name'] }}</div>
                                <div style="font-size: .8rem; opacity: .7;">/{{ $club['slug'] }}</div>
                            </div>
                            <x-filament::badge :color="$club['active'] ? 'success' : 'gray'">
                                {{ $club['active'] ? 'Actief' : 'Inactief' }}
                            </x-filament::badge>
                        </div>
                        <div style="display: flex; gap: 1rem; margin-top: .75rem; font-size: .85rem; opacity: .8;">
                            <span>{{ $club['users'] }} {{ $club['users'] === 1 ? 'gebruiker' : 'gebruikers' }}</span>
                            <span>{{ $club['modules'] }} {{ $club['modules'] === 1 ? 'module' : 'modules' }}</span>
                            <span>sinds {{ $club['created'] }}</span>
                        </div>
                    </x-filament::section>
                </a>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
