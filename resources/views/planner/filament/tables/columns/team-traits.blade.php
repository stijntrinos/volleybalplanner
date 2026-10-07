@php
    use App\Planner\Features\PlannerFeature;

    $team = $getRecord();
    $club = app(\App\Support\Tenancy\CurrentClub::class)->get();
    $duties = (bool) $club?->hasFeature(PlannerFeature::Duties);
    $volunteerPoints = (bool) $club?->hasFeature(PlannerFeature::VolunteerPoints);

    $automatic = collect([
        ['active' => $duties && $team->scores_own_match, 'label' => __('Eigen teller'), 'icon' => 'heroicon-m-pencil-square', 'color' => 'success'],
        ['active' => $duties && $team->provides_referee, 'label' => __('Nevobo fluit'), 'icon' => 'heroicon-m-flag', 'color' => 'info'],
        ['active' => $team->plays_on_two_courts, 'label' => __('2 velden'), 'icon' => 'heroicon-m-rectangle-group', 'color' => 'warning'],
    ])->where('active', true);
    $distributions = collect([
        ['active' => $duties && $team->include_in_duties, 'label' => __('FTZ'), 'icon' => 'heroicon-m-clipboard-document-check', 'color' => 'primary'],
        ['active' => $volunteerPoints && $team->include_volunteers, 'label' => __('Vrijwilligerspunten'), 'icon' => 'heroicon-m-scale', 'color' => 'gray'],
    ])->where('active', true);
@endphp

<div class="min-w-56 space-y-2 py-1">
    @if ($automatic->isNotEmpty())
        <div>
            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Automatisch bij thuiswedstrijd') }}</p>
            <div class="flex flex-wrap gap-1">
                @foreach ($automatic as $trait)
                    <x-filament::badge :color="$trait['color']" :icon="$trait['icon']" size="sm">
                        {{ $trait['label'] }}
                    </x-filament::badge>
                @endforeach
            </div>
        </div>
    @endif

    @if ($duties)
        <div>
            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ __('Meedoen aan verdeling') }}</p>
            <div class="flex flex-wrap gap-1">
                @forelse ($distributions as $trait)
                    <x-filament::badge :color="$trait['color']" :icon="$trait['icon']" size="sm">
                        {{ $trait['label'] }}
                    </x-filament::badge>
                @empty
                    <span class="text-xs text-gray-400">{{ __('Niet opgenomen') }}</span>
                @endforelse
            </div>
        </div>
    @elseif ($automatic->isEmpty())
        <span class="text-xs text-gray-400">—</span>
    @endif
</div>
