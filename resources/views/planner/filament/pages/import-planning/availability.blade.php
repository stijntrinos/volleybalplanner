<div class="grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_minmax(28rem,.85fr)] 2xl:items-start">
    <form wire:submit="analyzeAvailability" class="space-y-5">
        {{ $this->availabilityForm }}
        @include('planner::filament.pages.import-planning.check-bar', [
            'heading' => __('2. Beschikbaarheid controleren'), 'text' => __('Er wordt nog geen beschikbaarheid opgeslagen.'), 'target' => 'analyzeAvailability',
        ])
    </form>

    @if ($availabilityReport)
        @include('planner::filament.pages.import-planning.simple-report', [
            'result' => $availabilityReport,
            'validLabel' => __('Geldige regels'),
            'skippedLabel' => __('Lege regels'),
            'action' => 'importAvailability',
            'confirm' => __('Weet je zeker dat je deze beschikbaarheid wilt importeren?'),
            'button' => __('Beschikbaarheid importeren'),
        ])
    @else
        @include('planner::filament.pages.import-planning.empty-state', [
            'icon' => 'heroicon-o-building-office-2',
            'heading' => __('Nog geen beschikbaarheidscontrole'),
            'text' => __('Kies een bestand met de zaalhuur en controleer het voordat je importeert.'),
        ])
    @endif
</div>
