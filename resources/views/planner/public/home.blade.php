@component('planner::layouts.public', ['title' => null, 'description' => __('Wedstrijden, diensten en formulieren van :club.', ['club' => $club->name])])
    <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-16">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ $club->name }}</h1>
        <p class="mt-2 text-gray-600">{{ __('Bekijk de wedstrijdplanning of geef een wijziging door.') }}</p>

        <ul class="mt-8 grid gap-4 sm:grid-cols-2">
            @foreach ($pages as $page)
                <li>
                    <a href="{{ $page['url'] }}" class="block h-full border border-gray-200 bg-white p-5 transition hover:border-gray-400" style="text-decoration: none">
                        <span class="planner-text-primary block text-lg font-semibold">{{ $page['title'] }}</span>
                        <span class="mt-1 block text-sm text-gray-600">{{ $page['description'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        @if ($feeds !== [])
            <section class="mt-10">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('RSS-feeds') }}</h2>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($feeds as $feed)
                        <li><a class="planner-text-primary hover:underline" href="{{ $feed['url'] }}">{{ $feed['title'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endcomponent
