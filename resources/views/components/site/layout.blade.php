@props(['title' => null, 'description' => null, 'active' => null])
@php
    $company = config('site.company');
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
    <title>{{ $title ? $title.' · Volleybalplanner' : 'Volleybalplanner · Wedstrijdplanning voor volleybalverenigingen' }}</title>
    <meta name="description" content="{{ $description ?? 'Volleybalplanner: thuis- en uitwedstrijden, zaalbezetting en fluiten, tellen en zaaldienst voor de zaalcommissie van je volleybalvereniging. Elke nacht gesynchroniseerd met de Nevobo.' }}">
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect x="2" y="2" width="20" height="20" rx="5" fill="#10293B"/><circle cx="36" cy="12" r="8.5" fill="none" stroke="#0B7F8A" stroke-width="3"/><rect x="2" y="26" width="20" height="20" rx="5" fill="#10293B"/><rect x="26" y="26" width="20" height="20" rx="5" fill="#10293B"/></svg>') }}">
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
                @if (filled(config('site.privacy_url')))
                    <a href="{{ config('site.privacy_url') }}">Privacyverklaring</a>
                @endif
                @if (filled(config('site.terms_url')))
                    <a href="{{ config('site.terms_url') }}">Algemene voorwaarden</a>
                @endif
            </div>
        </div>
    </footer>
</body>
</html>
