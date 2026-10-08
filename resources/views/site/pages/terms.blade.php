@php
    $company = config('site.company') ?: 'Volleybalplanner';
@endphp

<x-site.layout title="Algemene voorwaarden" description="De voorwaarden voor het gebruik van Volleybalplanner door verenigingen.">
    <section class="page-intro">
        <div class="wrap">
            <span class="kicker">Voorwaarden</span>
            <h1 class="title">Algemene voorwaarden</h1>
            <p class="lead">De afspraken tussen {{ $company }} en de vereniging die Volleybalplanner gebruikt.</p>
        </div>
    </section>

    <article class="wrap legal">
        <h2>1. Het abonnement</h2>
        <p>Een vereniging neemt een abonnement per seizoen op een van de pakketten op de <a href="{{ route('site.pricing') }}">prijzenpagina</a>. Het pakket bepaalt de modules en het aantal teams, sporthallen en beheerders. Wie halverwege het seizoen instapt, betaalt naar rato. Overstappen naar een groter pakket kan altijd.</p>

        <h2>2. Gebruik</h2>
        <p>De vereniging zorgt dat alleen de mensen die ze uitnodigt toegang hebben, en dat die zorgvuldig omgaan met hun inloggegevens. Het is niet toegestaan de planner te gebruiken voor iets anders dan de planning van de vereniging, of te proberen toegang te krijgen tot gegevens van andere verenigingen.</p>

        <h2>3. Beschikbaarheid en ondersteuning</h2>
        <p>We doen ons best de planner altijd beschikbaar te houden en maken dagelijks back-ups. Gepland onderhoud doen we zoveel mogelijk buiten wedstrijddagen. Ondersteuning is er per e-mail.</p>

        <h2>4. Gegevens</h2>
        <p>De gegevens in de planner zijn van de vereniging. Wij verwerken ze alleen om de planner te leveren, volgens de verwerkersovereenkomst en de <a href="{{ route('site.privacy') }}">privacyverklaring</a>. Na afloop van het abonnement kan de vereniging haar gegevens exporteren; daarna verwijderen we ze.</p>

        <h2>5. Aansprakelijkheid</h2>
        <p>De vereniging blijft zelf verantwoordelijk voor haar planning en de afstemming met de Nevobo. Onze aansprakelijkheid is beperkt tot het bedrag dat de vereniging in het lopende seizoen voor het abonnement heeft betaald, behalve bij opzet of grove schuld.</p>

        <h2>6. Opzeggen</h2>
        <p>Een abonnement loopt per seizoen en wordt niet stilzwijgend verlengd zonder bericht vooraf. Opzeggen kan tot het einde van het lopende seizoen.</p>

        <h2>7. Wijzigingen</h2>
        <p>Wijzigen we deze voorwaarden, dan laten we dat de verenigingen ten minste een maand vooraf weten. Op deze voorwaarden is Nederlands recht van toepassing.</p>
    </article>
</x-site.layout>
