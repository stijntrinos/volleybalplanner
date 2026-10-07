<div class="grid gap-6 2xl:grid-cols-[minmax(0,1.15fr)_minmax(28rem,.85fr)] 2xl:items-start">
    <form wire:submit="analyzeDuties" class="space-y-5">
        {{ $this->dutyForm }}
        @include('planner::filament.pages.import-planning.check-bar', [
            'heading' => __('2. Dienstenbestand controleren'), 'text' => __('Er worden nog geen diensten opgeslagen.'), 'target' => 'analyzeDuties',
        ])
    </form>

    @if ($dutyReport)
        <x-filament::section :heading="__('3. Controleresultaat')" icon="heroicon-o-clipboard-document-check">
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['count' => $dutyReport['assigned']['referee'], 'label' => __('Fluitdiensten')],
                    ['count' => $dutyReport['assigned']['scorer'], 'label' => __('Teldiensten')],
                    ['count' => $dutyReport['assigned']['hall_duty'], 'label' => __('Zaaldiensten')],
                    ['count' => $dutyReport['unchanged'], 'label' => __('Al ingevuld, blijft staan')],
                    ['count' => $dutyReport['volleystars'], 'label' => __('VolleyStars-diensten (alleen geteld)')],
                    ['count' => count($dutyReport['unmatched']), 'label' => __('Zonder wedstrijd')],
                ] as $card)
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
                        <p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['count'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
                    </div>
                @endforeach
            </div>
            @include('planner::filament.pages.import-planning.row-errors', ['rowErrors' => $dutyReport['skipped'], 'heading' => __('Overgeslagen rijen')])
            @include('planner::filament.pages.import-planning.row-errors', ['rowErrors' => $dutyReport['unmatched'], 'heading' => __('Geen passende wedstrijd gevonden')])
            <div class="mt-6 border-t border-gray-200 pt-5 dark:border-white/10">
                <p class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ __('4. Import toepassen') }}</p>
                <x-filament::button color="success" icon="heroicon-o-arrow-down-tray" wire:click="importDuties" wire:loading.attr="disabled" wire:target="importDuties" wire:confirm="{{ __('Weet je zeker dat je deze diensten wilt importeren?') }}">{{ __('Diensten importeren') }}</x-filament::button>
            </div>
        </x-filament::section>
    @else
        @include('planner::filament.pages.import-planning.empty-state', [
            'icon' => 'heroicon-o-megaphone',
            'heading' => __('Nog geen dienstencontrole'),
            'text' => __('Kies de dienstenexport en een conceptversie, en controleer het bestand voordat je importeert.'),
        ])
    @endif
</div>
