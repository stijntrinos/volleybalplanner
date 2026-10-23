@props(['title' => null, 'description' => null, 'active' => null, 'noindex' => false])
@php
    $company = config('site.company');
    $pageTitle = $title ? $title.' · Volleybalplanner' : 'Volleybalplanner · Wedstrijdplanning voor volleybalverenigingen';
    $pageDescription = $description ?? 'Volleybalplanner: thuis- en uitwedstrijden, zaalbezetting en fluiten, tellen en zaaldienst voor de zaalcommissie van je volleybalvereniging. Elke nacht gesynchroniseerd met de Nevobo.';
    $nav = [
        'features' => ['Functies', route('site.features')],
        'pricing' => ['Prijzen', route('site.pricing')],
        'contact' => ['Contact', route('site.contact')],
    ];
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    @if ($noindex)
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ url()->current() }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Volleybalplanner">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('images/og-volleybalplanner.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Volleybalplanner: wedstrijdplanning zonder puzzelstress">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#2f4858">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">
    @vite('resources/css/site.css')
</head>
<body>
    <a href="#inhoud" class="skip-link">Naar de inhoud</a>

    <header class="site-header">
        <div class="wrap">
            <a href="{{ route('site.home') }}" class="brand">
                <x-site.mark />
                <span>Volleybalplanner</span>
            </a>
            <button type="button" class="menu-toggle" aria-expanded="false" aria-controls="site-menu" data-menu-toggle>
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><path class="menu-open" d="M4 7h16M4 12h16M4 17h16"></path><path class="menu-close" d="M6 6l12 12M18 6L6 18"></path></svg>
                <span class="sr-only">Menu</span>
            </button>
            <div class="site-menu" id="site-menu">
                <nav aria-label="Hoofdmenu" class="site-nav">
                    @foreach ($nav as $key => [$label, $url])
                        <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="header-actions">
                    <a href="{{ url('/app') }}" class="btn btn-outline">Inloggen</a>
                    @if ($active !== 'contact')
                        <a href="{{ route('site.contact') }}" class="btn btn-primary">Demo aanvragen</a>
                    @endif
                </div>
            </div>
        </div>
    </header>
    <script>
        (() => {
            const header = document.currentScript.previousElementSibling;
            const toggle = header.querySelector('[data-menu-toggle]');
            header.classList.add('has-menu-toggle');
            toggle.addEventListener('click', () => {
                const open = header.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        })();
    </script>

    <main id="inhoud">
        @if (request()->query('account') === 'verwijderd')
            <div class="wrap"><p class="site-notice" role="status">Je account is verwijderd. Bedankt voor het gebruiken van Volleybalplanner.</p></div>
        @endif
        {{ $slot }}
    </main>

    <footer class="site-footer">
        <div class="wrap">
            <div class="footer-top">
                <div class="footer-brand">
                    <a href="{{ route('site.home') }}" class="brand">
                        <x-site.mark :size="26" />
                        <span style="font-size: 18px">Volleybalplanner</span>
                    </a>
                    <p>Rustig overzicht voor de zaalcommissie.</p>
                </div>
                <nav aria-label="Voettekst" class="footer-nav">
                    <div>
                        <span>Product</span>
                        <a href="{{ route('site.features') }}">Functies</a>
                        <a href="{{ route('site.pricing') }}">Prijzen</a>
                        <a href="{{ url('/app') }}">Inloggen</a>
                    </div>
                    <div>
                        <span>Contact</span>
                        <a href="{{ route('site.contact') }}">Demo aanvragen</a>
                        <a href="{{ route('site.contact', ['onderwerp' => 'vraag']) }}">Een vraag stellen</a>
                        @if (filled(config('site.email')))
                            <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
                        @endif
                    </div>
                </nav>
            </div>
            <div class="footer-bottom">
                <span>© {{ now()->year }} {{ $company ?: 'Volleybalplanner' }}</span>
                <a href="{{ config('site.privacy_url') ?: route('site.privacy') }}">Privacyverklaring</a>
                <a href="{{ route('site.cookies') }}">{{ __('Cookies en voorkeuren') }}</a>
                <a href="{{ config('site.terms_url') ?: route('site.terms') }}">Algemene voorwaarden</a>
            </div>
        </div>
    </footer>
    <x-site.cookie-consent />
</body>
</html>
