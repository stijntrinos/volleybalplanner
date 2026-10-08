@props(['title', 'text', 'primary' => ['Demo aanvragen', null], 'secondary' => null])
<section class="wrap section no-top">
    <div class="cta">
        <div class="cta-text">
            <h2>{{ $title }}</h2>
            <p>{{ $text }}</p>
        </div>
        <div class="button-row">
            <a href="{{ $primary[1] ?? route('site.contact') }}" class="btn btn-lg btn-night">{{ $primary[0] }}</a>
            @if ($secondary)
                <a href="{{ $secondary[1] }}" class="btn btn-lg btn-night-outline">{{ $secondary[0] }}</a>
            @endif
        </div>
    </div>
</section>
