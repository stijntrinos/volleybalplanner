@inject('consent', 'App\Support\Privacy\CookieConsent')
<x-site.layout :title="__('Cookies en voorkeuren')" :description="__('Lees welke cookies Volleybalplanner gebruikt en pas je voorkeuren aan.')">
    <section class="page-intro">
        <div class="wrap">
            <span class="kicker">{{ __('Privacy') }}</span>
            <h1 class="title">{{ __('Cookies en voorkeuren') }}</h1>
            <p class="lead">{{ __('Jij bepaalt of we bezoek aan onze website mogen meten.') }}</p>
        </div>
    </section>
    <article class="wrap legal">
        @if (session('cookie-preferences-saved'))
            <p class="site-notice" role="status">{{ __('Je cookievoorkeuren zijn opgeslagen.') }}</p>
        @endif
        <h2>{{ __('Noodzakelijke cookies') }}</h2>
        <p>{{ __('Deze cookies houden je sessie bij, beveiligen formulieren en onthouden je cookiekeuze. Ze zijn nodig om de site te laten werken. Je cookiekeuze bewaren we 180 dagen.') }}</p>
        <h2>{{ __('Bezoekersstatistieken') }}</h2>
        <p>{{ __('Alleen met jouw toestemming meten we bezoek aan de openbare website: de bezochte pagina, het tijdstip, het soort apparaat en de website waar je vandaan komt. Een afgeschermde code laat ons bezoeken binnen dezelfde dag herkennen. We bewaren geen IP-adres, accountgegevens, volledige browserinformatie of queryparameters in de statistieken. De gegevens blijven in onze eigen database en worden na maximaal een jaar verwijderd. We gebruiken hiervoor geen Google Analytics of advertentietrackers.') }}</p>
        <p>{{ __('De besloten planner, het beheerpaneel en persoonlijke uitnodigingslinks worden niet gemeten. Een bezoek is een browsersessie per dag, geen herkenbare persoon. Statistieken geven daarom een indicatie van het bezoek.') }}</p>
        <h2>{{ __('Jouw voorkeur') }}</h2>
        <form method="POST" action="{{ route('site.cookies.store') }}" class="cookie-preferences">
            @csrf
            <input type="hidden" name="analytics" value="0">
            <label>
                <input type="checkbox" name="analytics" value="1" @checked($consent->analyticsAllowed(request()))>
                {{ __('Bezoekersstatistieken toestaan') }}
            </label>
            @error('analytics')<p role="alert">{{ $message }}</p>@enderror
            <p>{{ __('Je kunt je toestemming hier op ieder moment aanpassen of intrekken. Je nieuwe keuze geldt direct voor volgende paginabezoeken.') }}</p>
            <button class="btn btn-primary" type="submit">{{ __('Voorkeuren opslaan') }}</button>
        </form>
        <p><a href="{{ route('site.privacy') }}">{{ __('Lees ook onze privacyverklaring') }}</a></p>
    </article>
</x-site.layout>
