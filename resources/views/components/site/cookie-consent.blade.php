@inject('consent', 'App\Support\Privacy\CookieConsent')
@if ($consent->preference(request()) === null && ! request()->routeIs('site.cookies'))
    <section class="cookie-banner" aria-labelledby="cookie-banner-title">
        <div class="wrap cookie-banner-content">
            <div>
                <h2 id="cookie-banner-title">{{ __('Jij kiest welke cookies we gebruiken') }}</h2>
                <p>{{ __('Noodzakelijke cookies laten de site werken. Met jouw toestemming meten we ook welke pagina’s worden bezocht, zodat we de site kunnen verbeteren.') }}</p>
                <a href="{{ route('site.cookies') }}">{{ __('Uitleg en voorkeuren') }}</a>
            </div>
            <form method="POST" action="{{ route('site.cookies.store') }}" class="cookie-actions">
                @csrf
                <button class="btn btn-outline" name="analytics" value="0">{{ __('Alleen noodzakelijk') }}</button>
                <button class="btn btn-primary" name="analytics" value="1">{{ __('Ook statistieken toestaan') }}</button>
            </form>
        </div>
    </section>
@endif
