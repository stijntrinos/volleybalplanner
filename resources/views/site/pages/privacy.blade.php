@php
    $company = config('site.company') ?: 'Volleybalplanner';
    $email = config('site.email');
    $hosting = config('site.hosting');
@endphp

<x-site.layout title="Privacyverklaring" description="Hoe Volleybalplanner omgaat met persoonsgegevens van verenigingen, gebruikers en bezoekers.">
    <section class="page-intro">
        <div class="wrap">
            <span class="kicker">Privacy</span>
            <h1 class="title">Privacyverklaring</h1>
            <p class="lead">Hoe {{ $company }} omgaat met de gegevens van verenigingen, hun gebruikers en bezoekers van deze site.</p>
        </div>
    </section>

    <article class="wrap legal">
        <h2>Wie we zijn</h2>
        <p>{{ $company }} levert Volleybalplanner, een online planner voor de zaalcommissie van volleybalverenigingen. Voor de gegevens die een vereniging in de planner zet, is de vereniging verantwoordelijk en zijn wij verwerker; daarover sluiten we een verwerkersovereenkomst. Voor de gegevens van onze eigen klantcontacten en van deze website zijn wij verantwoordelijk.</p>

        <h2>Welke gegevens</h2>
        <ul>
            <li><strong>Accounts:</strong> naam, e-mailadres, een versleuteld wachtwoord en, als je die instelt, passkeys en de sleutel voor tweestapsverificatie.</li>
            <li><strong>Planningsgegevens van de vereniging:</strong> teams, wedstrijden, sporthallen, diensten en de namen en e-mailadressen van contactpersonen die een wijzigingsverzoek indienen.</li>
            <li><strong>Contact- en demoaanvragen:</strong> naam, vereniging, e-mailadres, eventueel telefoonnummer en je bericht.</li>
            <li><strong>Technische gegevens:</strong> logbestanden met IP-adres en tijdstip, nodig voor beveiliging en het tegengaan van misbruik.</li>
        </ul>

        <h2>Waarvoor</h2>
        <p>We gebruiken de gegevens om de planner te leveren en te beveiligen, om contact op te nemen over een aanvraag of abonnement, en om wettelijke verplichtingen na te komen. We verkopen geen gegevens en gebruiken ze niet voor advertenties. De site plaatst alleen cookies die nodig zijn om in te loggen en formulieren veilig te versturen.</p>

        <h2>Hoe lang</h2>
        <p>Accounts en planningsgegevens bewaren we zolang de vereniging klant is; daarna verwijderen we ze, tenzij de wet langer bewaren voorschrijft. Contactaanvragen bewaren we tot twee jaar na het laatste contact. Logbestanden en de activiteitenlog bewaren we maximaal een jaar.</p>

        <h2>Waar</h2>
        <p>{{ $hosting ? "De gegevens staan bij {$hosting}." : 'De gegevens staan bij een hostingpartij binnen de Europese Unie.' }} Met partijen die gegevens voor ons verwerken, zoals hosting en e-mailverzending, hebben we afspraken gemaakt die aan de AVG voldoen.</p>

        <h2>Je rechten</h2>
        <p>Je kunt je gegevens inzien, laten verbeteren of laten verwijderen, en bezwaar maken tegen een verwerking. Gaat het om gegevens in de planner van je vereniging, neem dan eerst contact op met de vereniging. Je kunt ook een klacht indienen bij de Autoriteit Persoonsgegevens.</p>

        <h2>Contact</h2>
        <p>Vragen over privacy? {{ $email ? 'Mail naar' : 'Neem contact op via' }} @if ($email)<a href="mailto:{{ $email }}">{{ $email }}</a>@else<a href="{{ route('site.contact', ['onderwerp' => 'vraag']) }}">het contactformulier</a>@endif.</p>
    </article>
</x-site.layout>
