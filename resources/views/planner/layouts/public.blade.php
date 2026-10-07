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
    @vite('resources/css/app.css')
    <style>
        :root { --planner-primary: {{ $primary }}; }
        .planner-public { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: #111827; background: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        .planner-public.is-embedded { min-height: 0; }
        .planner-public a { color: inherit; }
        .planner-header { border-bottom: 3px solid var(--planner-primary); background: #fff; }
        .planner-header-inner, .planner-footer-inner { max-width: 80rem; margin: 0 auto; padding: 0.875rem 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .planner-header-inner a { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; }
        .planner-logo { height: 2.75rem; width: auto; max-width: 12rem; object-fit: contain; }
        .planner-club-name { font-size: 1.25rem; font-weight: 700; letter-spacing: -0.01em; }
        .planner-main { flex: 1 0 auto; }
        .planner-footer { border-top: 1px solid #e5e7eb; background: #f9fafb; color: #6b7280; font-size: 0.8125rem; }
        .planner-footer-inner { justify-content: center; }
        /* Club colour utilities used by the public views. */
        .planner-text-primary { color: var(--planner-primary); }
        .planner-bg-primary { background-color: var(--planner-primary); color: #fff; }
        .planner-border-primary { border-color: var(--planner-primary) !important; }
        .planner-tint-primary { background-color: color-mix(in srgb, var(--planner-primary) 8%, white); }
        .planner-accent-border { border-color: color-mix(in srgb, var(--planner-primary) 45%, white); }
        .planner-public :is(input, select, textarea):focus { outline: 2px solid var(--planner-primary); outline-offset: 0; border-color: transparent; }
        .planner-public input[type=radio] { accent-color: var(--planner-primary); }
        .planner-button { display: inline-flex; align-items: center; justify-content: center; padding: 0.75rem 1.5rem; font-weight: 600; background: var(--planner-primary); color: #fff; transition: opacity .15s; }
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
                        <img class="planner-logo" src="{{ $logo }}" alt="{{ $club->name }}">
                    @else
                        <span class="planner-club-name">{{ $club->name }}</span>
                    @endif
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
                {{ __('Mogelijk gemaakt door :app', ['app' => config('app.name')]) }}
            </div>
        </footer>
    @endunless
</body>
</html>
