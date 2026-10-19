@php
    $details = [
        [
            'kicker' => 'Planning · thuiswedstrijden',
            'title' => 'De dag in één beeld',
            'text' => 'Per wedstrijddag een rooster met een kolom per veld en de tijd van boven naar beneden. Zaaldienst, trainingen, VolleyStars en zaalactiviteiten staan er gewoon tussen.',
            'points' => ['Slepen met conflictcontrole, daarna 15 seconden om ongedaan te maken', 'Zoeken op team of wedstrijdcode met ⌘K / Ctrl+K', 'CenterCourt: één brede kaart over twee velden'],
            'mock' => 'Zaterdag 10-10-2026 · Sporthal De Haven',
            'rows' => [
                ['09:00', 'MC 1 – Smash ’88 MC 1 · veld 1', 'Gepland', 'ok'],
                ['10:45', 'JB 1 – Zuiderzee JB 1 · veld 1', 'Gepland', 'ok'],
                ['14:30', 'DS 1 – Netwerk DS 1 · CC', 'CenterCourt', 'muted'],
                ['17:00', 'HS 2 – Blok 3 HS 2 · veld 2', 'Botst met training', 'warn'],
            ],
        ],
        [
            'kicker' => 'Planning · wijzigingen',
            'title' => 'Verplaatsen zonder gedoe',
            'text' => 'Schuiven op dezelfde dag gaat direct. Naar een andere dag wordt een voorstel: de wedstrijd blijft op zijn plek, de nieuwe plek wordt alvast bezet gehouden.',
            'points' => ['Assistent controleert veld, trainingen, teams en rusttijd', 'Na “Bevestigd” verhuist de wedstrijd echt', 'Ziet de Nevobo-sync de nieuwe datum, dan bevestigt die vanzelf'],
            'mock' => 'Lopende wijzigingen',
            'rows' => [
                ['17-10', 'MA 2 – Smash ’88 MA 1 · 14:30', 'Moet verplaatst worden', 'warn'],
                ['24-10', 'HS 1 – Zuiderzee HS 1 · 20:45', 'Voorstel', 'muted'],
                ['12-12', 'DS 9 – DS 10 · 19:30', 'Bevestigd', 'ok'],
            ],
        ],
        [
            'kicker' => 'Planning · FTZ',
            'title' => 'Fluiten, tellen en zaaldienst',
            'text' => 'Eén klik voor een volledig voorstel. De planner kijkt naar wedstrijden, trainingen, uitwedstrijden en teamregels, en verdeelt eerlijk op vrijwilligerspunten.',
            'points' => ['Per dag controleren, vastzetten of opnieuw laten vullen', 'Handmatige keuzes blijven altijd staan', 'Taken-dashboard: geleverd tegenover verbruikt per team'],
            'mock' => 'Taken-dashboard · seizoen 2026-2027',
            'rows' => [
                ['DS 1', 'Verbruikt 312 · geleverd 157', 'Tekort', 'warn'],
                ['HS 3', 'Verbruikt 140 · geleverd 152', 'In balans', 'ok'],
                ['MB 1', 'Verbruikt 96 · geleverd 101', 'In balans', 'ok'],
            ],
        ],
        [
            'kicker' => 'Invoer & controle',
            'title' => 'De Nevobo houdt je bij, niet andersom',
            'text' => 'Elke nacht om 06:00 vergelijkt de planner met de Nevobo-feeds. Wat de bond verzette, wat nog moet verhuizen en welke verzoeken binnenkwamen zie je direct na inloggen.',
            'points' => ['Nevobo overnemen met één klik', 'Bewust eigen planning houden: de melding verdwijnt', 'Afvinken wat al bij de bond is doorgevoerd'],
            'mock' => 'Nevobo-sync · vandaag 06:00',
            'rows' => [
                ['3', 'Tijden automatisch bijgewerkt', 'Verwerkt', 'ok'],
                ['1', 'Datum wijkt af van eigen planning', 'Beslissen', 'warn'],
                ['2', 'Nieuwe wijzigingsverzoeken', 'Open', 'muted'],
            ],
        ],
    ];

    $groups = [
        'Planning' => [
            ['Thuiswedstrijden', 'Dagrooster per sporthal en veld, slepen met conflictcontrole, assistent voor verplaatsen.'],
            ['Uitwedstrijden', 'Actuele en voorgestelde datum, status van overleg en conflicten met eigen wedstrijden.'],
            ['Sporthalbezetting', 'Beschikbaarheid per hal als lijst of kalender, met snelfilters op conflicten.'],
            ['FTZ-planner', 'Voorstel voor fluiten, tellen en zaaldienst volgens teamregels en punten.'],
            ['Zaaldiensten en VolleyStars', 'Wie per tijdsblok verantwoordelijk is; opbouw, tellen en afbouw verdelen.'],
            ['Taken-dashboard', 'Vrijwilligerspunten per team: geleverd tegenover verbruikt.'],
            ['Exporteren', 'Excel, PDF, conflictrapport, dagschema en FTZ-Excel.'],
        ],
        'Invoer & controle' => [
            ['Planning importeren', 'Nevobo-bestand controleren en als nieuw of bijgewerkt concept toepassen, ook trainingen.'],
            ['Bondskalender', 'Vakanties, feestdagen en bekerrondes die de planning raken.'],
            ['Nevobo-wijzigingen', 'Wat jullie anders plannen dan de bond, tot het daar verwerkt is.'],
            ['Wijzigingsverzoeken', 'Accepteren, afwijzen of parkeren, met notitie.'],
            ['Planningversies', 'Concepten, actief maken, dupliceren en archiveren.'],
        ],
        'Website' => [
            ['Publiek wedstrijdprogramma', 'Per maand en per sporthal, met filters die in de link staan.'],
            ['Verzoekformulieren', 'Vaste links voor wedstrijd verzetten en dienst wijzigen.'],
            ['RSS-feeds', 'Centraal, thuis, per sporthal, team of dag.'],
            ['Insluiten op de clubsite', 'Wedstrijdprogramma en formulieren zonder kop en voet, in de clubkleur.'],
        ],
        'Stamdata & instellingen' => [
            ['Teams', 'Vaste speeldag, tijd en veld, CenterCourt, FTZ-deelname en punten.'],
            ['Sporthallen en beschikbaarheid', 'Locaties, velden, openingstijden en uitzonderingen.'],
            ['Trainingsschema', 'Vast weekschema voor de conflictcontrole.'],
            ['Zaalactiviteiten', 'Hal of veld blokkeren buiten wedstrijden.'],
            ['Clubinstellingen', 'Seizoen, hoofdsporthal, FTZ-regels, punten, clubkleur en logo.'],
        ],
    ];
@endphp

<x-site.layout title="Functies" active="features" description="Alle functies van Volleybalplanner: dagrooster per veld, verplaatsingsassistent, FTZ-planner, Nevobo-sync, wijzigingsverzoeken en het publieke wedstrijdprogramma.">
    <section class="page-intro">
        <div class="wrap">
            <span class="kicker">Functies</span>
            <h1 class="title">Alles wat de zaalcommissie doet, op één plek</h1>
            <p class="lead">Van het inlezen van het Nevobo-programma tot het dagschema op het scherm in de hal. Twintig onderdelen in het beheer, en de plekken op de website waar de planning terugkomt.</p>
        </div>
    </section>

    <section class="wrap section" style="padding-top: 72px; padding-bottom: 24px; gap: 24px">
        @foreach ($details as $detail)
            <article class="card detail">
                <div class="detail-text">
                    <span class="kicker">{{ $detail['kicker'] }}</span>
                    <h2>{{ $detail['title'] }}</h2>
                    <p>{{ $detail['text'] }}</p>
                    <ul class="check-list">
                        @foreach ($detail['points'] as $point)
                            <li><x-site.check /><span>{{ $point }}</span></li>
                        @endforeach
                    </ul>
                </div>
                <div class="detail-mock" role="img" aria-label="Voorbeeld: {{ $detail['mock'] }}">
                    <strong>{{ $detail['mock'] }}</strong>
                    @foreach ($detail['rows'] as [$a, $b, $c, $tone])
                        <div class="detail-row">
                            <span>{{ $a }}</span>
                            <span>{{ $b }}</span>
                            <span class="badge {{ $tone }}">{{ $c }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        @endforeach
    </section>

    <section class="wrap section" style="padding-top: 64px; gap: 28px">
        <h2 class="section-title" style="font-size: 34px">Alle functies op een rij</h2>
        <div class="grid grid-270">
            @foreach ($groups as $name => $items)
                <div class="card group">
                    <span class="kicker">{{ $name }}</span>
                    @foreach ($items as [$title, $text])
                        <div class="group-item"><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <x-site.cta title="Zien hoe het werkt voor jullie club?" text="We lopen de planner door met jullie eigen sporthallen en teams." />
</x-site.layout>
