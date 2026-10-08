@php
    $columns = [
        ['title' => 'Veld 1', 'sub' => 'Sporthal De Haven', 'duty' => false, 'cards' => [
            ['09:00', '#XCHC1', 'MC 1', 'Smash ’88 MC 1', 'DS 4', 'MC 1'],
            ['10:45', '#JBHA1', 'JB 1', 'Zuiderzee JB 1', 'HS 3', 'JB 1'],
            ['12:30', '#JAHA1', 'JA 1', 'Blok 3 JA 1', 'HS 5', 'MC 1'],
        ]],
        ['title' => 'Veld 2', 'sub' => 'Sporthal De Haven', 'duty' => false, 'cards' => [
            ['09:00', '#XC2J1', 'JC 2', 'Netwerk JC 1', 'DS 8', 'JC 2'],
            ['10:45', '#MBHC1', 'MB 1', 'Smash ’88 MB 1', 'DS 1', 'MB 1'],
            ['12:30', '#D1L-D', 'DS 4', 'Zuiderzee DS 2', 'HS 2', 'JC 2'],
        ]],
        ['title' => 'Zaaldienst', 'sub' => 'Sporthal De Haven', 'duty' => true, 'cards' => [
            ['HS 3', '08:15–10:45'], ['HS 3', '10:45–12:30'], ['HS 5', '12:30–14:30'],
        ]],
    ];

    $steps = [
        ['Importeren', 'Lees het Nevobo-programma in als concept, met controle vooraf.'],
        ['Plannen', 'Per sporthal en veld, per dag. Slepen mag, de planner bewaakt de rest.'],
        ['Controleren', 'Conflicten zijn direct zichtbaar en diensten worden eerlijk verdeeld.'],
        ['Publiceren', 'Maak de versie actief. Website, feeds en schema’s volgen vanzelf.'],
    ];

    $features = [
        ['M7 7h11l-3-3M17 17H6l3 3', 'Slim verplaatsen', 'De verplaatsingsassistent toont alleen momenten die echt vrij zijn. Een andere dag wordt eerst een voorstel dat op akkoord wacht.'],
        ['M4 19c0-3 2.7-5 6-5s6 2 6 5M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M17 11a3 3 0 1 0 0-6M20 19c0-2.2-1.2-3.8-3-4.6', 'Fluiten, tellen en zaaldienst', 'Eén klik voor een volledig voorstel, met teamregels en vrijwilligerspunten. Handmatige keuzes blijven staan.'],
        ['M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4', 'Nevobo-sync elke nacht', 'Om 06:00 vergelijkt de planner met de Nevobo-feeds. Kleine wijzigingen gaan vanzelf, afwijkingen komen op het dashboard.'],
        ['M4 13l2.5-8h11l2.5 8v6H4zM4 13h5l1 2h4l1-2h5', 'Wijzigingsverzoeken', 'Teams vragen via een vaste link om een andere datum of dienst. Jij beslist, met reden en voorstel erbij.'],
        ['M3 5h18v14H3zM12 5v3M12 16v3', 'CenterCourt', 'Een wedstrijd op twee velden staat als één brede kaart in de planner en als “CC” op de website.'],
        ['M4 5h16v14H4zM4 15l4-4 4 4 3-3 5 5', 'Klaar om te delen', 'Dagschema als afbeelding voor scherm en socials, A4-PDF, Excel per sporthal en een conflictrapport.'],
    ];

    $schedule = [
        ['09:00', 'MC 1 – Smash ’88', 'Fluiten DS 4 · Tellen MC 1'],
        ['09:00', 'JC 2 – Netwerk', 'Fluiten DS 8 · Tellen JC 2'],
        ['09:00', 'JB 3 – Blok 3', 'Fluiten DS 4 · Tellen JB 3'],
        ['10:45', 'JB 1 – Zuiderzee', 'Fluiten HS 3 · Tellen JB 1'],
        ['10:45', 'MB 1 – Smash ’88', 'Fluiten DS 1 · Tellen MB 1'],
        ['10:45', 'MA 2 – Netwerk', 'Fluiten DS 1 · Tellen MA 2'],
    ];

    $publicItems = [
        ['Publiek wedstrijdprogramma', 'Alle thuiswedstrijden en diensten vanaf vandaag, per maand en per sporthal, met filters op team en zaal. Filters staan in de link, dus een team deelt zijn eigen overzicht.'],
        ['RSS-feeds voor app en schermen', 'Alles, alleen thuis, per sporthal, per team of per dag. Gewijzigde wedstrijden zijn herkenbaar gemarkeerd.'],
        ['Programmaboekje', 'Het boekje voor een toernooi- of thuisdag haalt het dagschema uit de actieve planning: online te bladeren of als PDF.'],
    ];

    $clubColours = ['#7A2E3A' => 'Bordeaux', '#1E5AA8' => 'Blauw', '#2F6B3A' => 'Groen', '#0B7F8A' => 'Teal'];
@endphp

<x-site.layout>
    <section class="hero">
        <div class="wrap">
            <div class="hero-text">
                <span class="hero-pill">Voor de zaalcommissie van volleybalverenigingen</span>
                <h1 class="display">Wedstrijdplanning zonder puzzelstress</h1>
                <p class="lead">Eén rustige plek voor thuis- en uitwedstrijden, zaalbezetting, een eerlijke verdeling van fluiten, tellen en zaaldienst, en alles wat leden op de website zien.</p>
                <div class="button-row">
                    <a href="{{ route('site.contact') }}" class="btn btn-lg btn-primary">Demo aanvragen</a>
                    <a href="{{ route('site.features') }}" class="btn btn-lg btn-outline">Bekijk alle functies</a>
                </div>
                <div class="hero-note">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="#0B7F8A" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 11a8 8 0 0 0-14.3-4.9L4 8M4 4v4h4M4 13a8 8 0 0 0 14.3 4.9L20 16M20 20v-4h-4"></path></svg>
                    <span>Elke nacht gesynchroniseerd met de Nevobo</span>
                </div>
            </div>

            <div class="mock" role="img" aria-label="Voorbeeld van het dagrooster: drie kolommen met wedstrijden en zaaldiensten op zaterdag 10 oktober">
                <div class="mock-bar">
                    <div class="mock-date"><small>Zaterdag</small><strong>10-10-2026</strong></div>
                    <span class="mock-chip is-brand">v40 · concept</span>
                    <span class="mock-chip">0 conflicten</span>
                </div>
                <div class="mock-columns">
                    @foreach ($columns as $column)
                        <div class="mock-column">
                            <div @class(['mock-head', 'is-duty' => $column['duty']])><strong>{{ $column['title'] }}</strong><small>{{ $column['sub'] }}</small></div>
                            @foreach ($column['cards'] as $card)
                                @if ($column['duty'])
                                    <div class="mock-card is-duty">
                                        <div class="mock-card-top"><span>{{ $card[0] }}</span></div>
                                        <strong>Zaaldienst</strong>
                                        <small>{{ $card[1] }}</small>
                                    </div>
                                @else
                                    <div class="mock-card">
                                        <div class="mock-card-top"><span>{{ $card[0] }}</span><span>{{ $card[1] }}</span></div>
                                        <strong>{{ $card[2] }}</strong>
                                        <small>{{ $card[3] }}</small>
                                        <div class="mock-chips"><span>F: {{ $card[4] }}</span><span>T: {{ $card[5] }}</span></div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="wrap section tight-bottom">
        <div class="section-head">
            <span class="kicker">Zo werkt het</span>
            <h2 class="section-title">Je werkt in een concept. Pas als alles klopt, maak je het actief.</h2>
        </div>
        <div class="grid grid-230">
            @foreach ($steps as $index => [$title, $text])
                <div class="card step">
                    <span class="step-number">{{ $index + 1 }}</span>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="wrap section tight-top">
        <div class="card flow">
            <div class="flow-item is-draft">
                <span class="flow-kicker">Concept</span>
                <strong>Hier werk je in</strong>
                <p>Alleen een concept is te bewerken. Je kunt er meerdere naast elkaar hebben.</p>
            </div>
            <div class="flow-item is-live">
                <span class="flow-kicker">Actieve planning</span>
                <strong>Precies één versie</strong>
                <p>Publiceren lukt niet zolang er harde conflicten zijn.</p>
            </div>
            <div class="flow-item is-public">
                <span class="flow-kicker">Publiek</span>
                <strong>Wat leden lezen</strong>
                <p>Wedstrijdpagina, RSS-feeds, programmaboekje en verzoekformulieren lezen deze versie.</p>
            </div>
        </div>
        <p class="footnote">De vorige actieve versie wordt bij publiceren automatisch gearchiveerd. Terugzetten naar concept of dupliceren kan altijd.</p>
    </section>

    <section class="band">
        <div class="wrap section">
            <div class="section-head">
                <span class="kicker">Waar het verschil zit</span>
                <h2 class="section-title">Gebouwd voor hoe een zaalcommissie echt werkt</h2>
            </div>
            <div class="grid grid-320">
                @foreach ($features as [$icon, $title, $text])
                    <div class="feature">
                        <span class="feature-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"></path></svg></span>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="wrap section">
        <div class="split">
            <div class="split-text">
                <span class="kicker">Jullie club, jullie kleuren</span>
                <h2 class="section-title">Dagschema’s die eruitzien als jullie eigen</h2>
                <p>Stel je clubkleur en logo één keer in. Dagschema’s voor het scherm in de hal, de PDF voor het prikbord en de publieke wedstrijdpagina nemen ze over. Volleybalplanner staat er klein en in grijs onder.</p>
                <ul class="check-list">
                    <li><x-site.check /><span>Afbeelding voor scherm en socials, A4-PDF en Excel per sporthal</span></li>
                    <li><x-site.check /><span>Teamsponsoren per wedstrijd en tot acht extra sponsorlogo’s</span></li>
                    <li><x-site.check /><span>Zaalwacht en voettekst per export in te stellen</span></li>
                </ul>
            </div>
            <div class="card schedule-mock" style="--club: #7A2E3A" data-club-preview>
                <div class="schedule-mock-head">
                    <span class="schedule-mock-logo">LOGO</span>
                    <div><strong>Sporthal De Haven</strong><small>Zaterdag 10-10-2026</small></div>
                </div>
                <div class="schedule-mock-grid">
                    @foreach ($schedule as [$time, $match, $duty])
                        <div class="schedule-mock-cell">
                            <span>{{ $time }}</span>
                            <div><strong>{{ $match }}</strong><small>{{ $duty }}</small></div>
                        </div>
                    @endforeach
                </div>
                <div class="club-swatches" role="group" aria-label="Probeer een clubkleur">
                    @foreach ($clubColours as $colour => $name)
                        <button type="button" style="background: {{ $colour }}" aria-label="Clubkleur {{ $name }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" data-colour="{{ $colour }}"></button>
                    @endforeach
                </div>
                <div class="schedule-mock-credit">
                    <span>Gepland met</span>
                    <x-site.mark :size="12" ink="#7A8893" ball="#7A8893" />
                    <strong>Volleybalplanner</strong>
                </div>
            </div>
        </div>
    </section>

    <section class="wrap section no-top">
        <div class="section-head">
            <span class="kicker">Wat leden zien</span>
            <h2 class="section-title">Publiceer je een nieuwe versie, dan is de site direct bij</h2>
        </div>
        <div class="grid grid-300">
            @foreach ($publicItems as [$title, $text])
                <div class="card public-item">
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <x-site.cta
        title="Klaar voor een rustig seizoen?"
        text="We laten je de planner zien met jullie eigen Nevobo-programma. Je zit binnen een kwartier in je eerste concept."
        :secondary="['Bekijk de prijzen', route('site.pricing')]"
    />

    <script>
        document.querySelectorAll('[data-club-preview]').forEach((preview) => {
            preview.querySelectorAll('[data-colour]').forEach((button) => {
                button.addEventListener('click', () => {
                    preview.style.setProperty('--club', button.dataset.colour);
                    preview.querySelectorAll('[data-colour]').forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
                });
            });
        });
    </script>
</x-site.layout>
