<div class="grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_minmax(28rem,.85fr)] 2xl:items-start">
    <form wire:submit="analyzeTrainings" class="space-y-5">
        {{ $this->trainingForm }}
        @include('planner::filament.pages.import-planning.check-bar', [
            'heading' => __('2. Trainingsbestand controleren'), 'text' => __('Er worden nog geen trainingen opgeslagen.'), 'target' => 'analyzeTrainings',
        ])
    </form>

    @if ($trainingReport)
        @include('planner::filament.pages.import-planning.simple-report', [
            'result' => $trainingReport,
            'validLabel' => __('Geldige trainingen'),
            'skippedLabel' => __('Regels zonder team'),
            'action' => 'importTrainings',
            'confirm' => __('Weet je zeker dat je dit trainingsschema wilt importeren?'),
            'button' => __('Trainingen importeren'),
        ])
    @else
        @include('planner::filament.pages.import-planning.empty-state', [
            'icon' => 'heroicon-o-academic-cap',
            'heading' => __('Nog geen trainingscontrole'),
            'text' => __('Kies een trainingsbestand en controleer het voordat je importeert.'),
        ])
    @endif
</div>
