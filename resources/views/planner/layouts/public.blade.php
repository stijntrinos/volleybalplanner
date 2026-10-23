@php
    use App\Planner\Models\PlannerSetting;
    use App\Planner\Support\PrimaryColor;
    use App\Support\Tenancy\CurrentClub;
    use Illuminate\Support\Facades\Storage;

    $club = app(CurrentClub::class)->require();
    $settings = PlannerSetting::current();
    $primary = PrimaryColor::from($settings->primary_color);
    $logo = filled($settings->logo_path) ? Storage::disk('public')->url($settings->logo_path) : null;
    $embed = request()->boolean('embed');
    $pageTitle = isset($title) && filled($title) ? $title.' · '.$club->name : $club->name;
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    @if (! empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    @if (! empty($noIndex) || $embed)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=outfit:400,500,600,700&display=swap" rel="stylesheet">
    @vite('resources/css/app.css')
    <style>
        :root { --planner-primary: {{ $primary }}; }
        .planner-public { color: #15232c; min-height: 100vh; display: flex; flex-direction: column; -webkit-font-smoothing: antialiased; }
        .planner-public.is-embedded { min-height: 0; }
        .planner-public a { color: inherit; }
        .planner-header { background: var(--planner-primary); color: #fff; }
        .planner-header-inner, .planner-footer-inner { max-width: 80rem; margin: 0 auto; padding: 0.875rem 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .planner-header-inner a { display: flex; align-items: center; gap: 0.875rem; text-decoration: none; color: #fff; }
        .planner-logo-tile { display: flex; align-items: center; justify-content: center; height: 3rem; min-width: 3rem; padding: 0.25rem 0.5rem; border-radius: 10px; background: #fff; }
        .planner-logo { height: 2.5rem; width: auto; max-width: 10rem; object-fit: contain; }
        .planner-club-name { font-size: 1.25rem; font-weight: 600; letter-spacing: -0.01em; }
        .planner-main { flex: 1 0 auto; }
        .planner-footer { border-top: 1px solid #d6dfe3; background: #fff; color: #7d909a; font-size: 0.8125rem; }
        .planner-footer-inner { justify-content: center; gap: 0.375rem; }
        .planner-footer-inner a { display: inline-flex; align-items: center; gap: 0.375rem; color: #7d909a; text-decoration: none; }
        .planner-footer-inner strong { font-weight: 600; }
        /* Club colour utilities used by the public views. */
        .planner-text-primary, .planner-public a.planner-text-primary { color: var(--planner-primary); }
        .planner-bg-primary { background-color: var(--planner-primary); color: #fff; }
        .planner-border-primary { border-color: var(--planner-primary) !important; }
        .planner-tint-primary { background-color: color-mix(in srgb, var(--planner-primary) 8%, white); }
        .planner-accent-border { border-color: color-mix(in srgb, var(--planner-primary) 45%, white); }
        .planner-public :is(input:not([type=radio], [type=checkbox]), select, textarea):focus { outline: 2px solid var(--planner-primary); outline-offset: 0; border-color: transparent; }
        .planner-public input:is([type=radio], [type=checkbox]):focus { outline: 0; }
        .planner-public .planner-option:has(input:focus-visible) { outline: 2px solid var(--planner-primary); outline-offset: 2px; }
        .planner-public input[type=radio] { accent-color: var(--planner-primary); }
        .planner-button { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 1.5rem; font-weight: 600; background: var(--planner-primary); color: #fff; transition: opacity .15s; }
        .planner-button:hover { opacity: .9; }
        .planner-button:disabled { opacity: .6; }
    </style>
</head>
<body class="planner-public {{ $embed ? 'is-embedded' : '' }}">
    @unless ($embed)
        <header class="planner-header">
            <div class="planner-header-inner">
                <a href="{{ route('planner.public.home', ['club' => $club]) }}">
                    @if ($logo)
                        <span class="planner-logo-tile"><img class="planner-logo" src="{{ $logo }}" alt=""></span>
                    @endif
                    <span class="planner-club-name">{{ $club->name }}</span>
                </a>
            </div>
        </header>
    @endunless

    <main class="planner-main">
        {{ $slot }}
    </main>

    @unless ($embed)
        <footer class="planner-footer">
            <div class="planner-footer-inner">
                <a href="{{ url('/') }}">
                    <span>{{ __('Gepland met') }}</span>
                    <svg viewBox="0 0 48 48" width="14" height="14" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" fill="#7D909A"></rect><g transform="translate(36 12)" fill="none" stroke="#7D909A" stroke-width="3.8" stroke-linecap="round"><circle r="8.1"></circle><path d="M0 0 Q3.98 -3.34 0 -8.1"></path><path d="M0 0 Q0.9 5.12 7.01 4.05"></path><path d="M0 0 Q-4.89 -1.78 -7.01 4.05"></path></g><rect x="2" y="26" width="20" height="20" rx="5" fill="#7D909A"></rect><rect x="26" y="26" width="20" height="20" rx="5" fill="#7D909A"></rect></svg>
                    <strong>{{ config('app.name') }}</strong>
                </a>
            </div>
        </footer>
    @endunless
</body>
</html>
