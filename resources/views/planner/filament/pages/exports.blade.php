<x-filament-panels::page>
    <x-filament::section
        :heading="__('Exportbron')"
        :description="__('Alle downloads op deze pagina gebruiken dezelfde geselecteerde planningversie.')"
        icon="heroicon-o-document-duplicate"
    >
        <div class="grid gap-4 lg:grid-cols-[minmax(16rem,1fr)_minmax(0,2fr)] lg:items-end">
            <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                {{ __('Planningversie') }}
                <select wire:model.live="versionId" class="fi-select-input rounded-lg border-gray-300 dark:bg-gray-800">
                    @foreach ($this->versions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex items-start gap-3 rounded-lg bg-primary-50 p-4 text-sm text-primary-800 dark:bg-primary-500/10 dark:text-primary-300">
                <x-filament::icon icon="heroicon-o-information-circle" class="h-5 w-5 shrink-0" />
                <p>{{ __('Controleer vóór het downloaden of hierboven de juiste concept- of gepubliceerde versie is geselecteerd.') }}</p>
            </div>
        </div>
    </x-filament::section>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-filament::section
            :heading="__('Complete wedstrijdplanning')"
            :description="__('Exporteer de volledige geselecteerde planning voor verdere verwerking of verspreiding.')"
            icon="heroicon-o-calendar-days"
        >
            <div class="space-y-3">
                @foreach ([
                    ['action' => 'planningExcel', 'title' => __('Planning Excel'), 'badge' => 'XLSX', 'text' => __('Een apart tabblad per sporthal, geschikt voor verdere bewerking.'), 'icon' => 'heroicon-o-table-cells', 'tone' => 'success'],
                    ['action' => 'planningPdf', 'title' => __('Planning PDF'), 'badge' => 'PDF', 'text' => __('Liggende A4-versie om te delen of direct af te drukken.'), 'icon' => 'heroicon-o-document-text', 'tone' => 'danger'],
                ] as $export)
                    <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div @class([
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-lg',
                            'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400' => $export['tone'] === 'success',
                            'bg-danger-50 text-danger-600 dark:bg-danger-500/10 dark:text-danger-400' => $export['tone'] === 'danger',
                        ])>
                            <x-filament::icon :icon="$export['icon']" class="h-6 w-6" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2"><h3 class="font-semibold text-gray-950 dark:text-white">{{ $export['title'] }}</h3><span class="rounded bg-gray-100 px-1.5 py-0.5 text-[.65rem] font-bold text-gray-700 dark:bg-white/10 dark:text-gray-300">{{ $export['badge'] }}</span></div>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $export['text'] }}</p>
                        </div>
                        <x-filament::button wire:click="{{ $export['action'] }}" wire:loading.attr="disabled" wire:target="{{ $export['action'] }}" :color="$export['tone'] === 'success' ? 'success' : 'gray'" icon="heroicon-o-arrow-down-tray">
                            <span wire:loading.remove wire:target="{{ $export['action'] }}">{{ __('Downloaden') }}</span><span wire:loading wire:target="{{ $export['action'] }}">{{ __('Maken…') }}</span>
                        </x-filament::button>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            :heading="__('Planning controleren')"
            :description="__('Controleer harde conflicten en aandachtspunten voordat je de planning deelt.')"
            icon="heroicon-o-shield-exclamation"
        >
            <div class="flex h-full flex-col rounded-xl border border-warning-200 bg-warning-50/50 p-5 dark:border-warning-500/20 dark:bg-warning-500/5">
                <div class="mb-5 flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-6 w-6" /></div>
                    <div><div class="flex items-center gap-2"><h3 class="font-semibold text-gray-950 dark:text-white">{{ __('Conflictrapport') }}</h3><span class="rounded bg-success-50 px-1.5 py-0.5 text-[.65rem] font-bold text-success-700 dark:bg-success-500/10 dark:text-success-400">XLSX</span></div><p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ __('Overzicht van dubbele veldbezetting, ontbrekende gegevens en overige waarschuwingen.') }}</p></div>
                </div>
                <div class="mt-auto"><x-filament::button wire:click="conflictsExcel" wire:loading.attr="disabled" wire:target="conflictsExcel" color="warning" icon="heroicon-o-arrow-down-tray"><span wire:loading.remove wire:target="conflictsExcel">{{ __('Conflictrapport downloaden') }}</span><span wire:loading wire:target="conflictsExcel">{{ __('Rapport maken…') }}</span></x-filament::button></div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section
        :heading="__('Eén wedstrijddag exporteren')"
        :description="__('Kies eerst een wedstrijddag en daarna de sporthal die je wilt exporteren.')"
        icon="heroicon-o-calendar-date-range"
    >
        <div class="grid gap-4 xl:grid-cols-[minmax(19rem,.75fr)_minmax(0,2.25fr)] xl:items-stretch">
            <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/5">
                <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('Wedstrijddag') }}
                    <select wire:model.live="date" class="fi-select-input rounded-lg border-gray-300 bg-white dark:bg-gray-800">
                        @forelse ($this->matchDays as $day => $label)
                            <option value="{{ $day }}">{{ $label }}</option>
                        @empty
                            <option value="{{ $date }}">{{ __('Geen wedstrijddagen beschikbaar') }}</option>
                        @endforelse
                    </select>
                </label>
                <label class="mt-4 grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
                    {{ __('Sporthal') }}
                    <select wire:model.live="venueId" class="fi-select-input rounded-lg border-gray-300 bg-white dark:bg-gray-800">
                        @forelse ($this->venues as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @empty
                            <option value="">{{ __('Geen sporthal beschikbaar') }}</option>
                        @endforelse
                    </select>
                </label>
                <div class="mt-3 flex items-start gap-2 text-xs text-gray-500 dark:text-gray-400">
                    <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 shrink-0" />
                    <p>{{ __('De downloads bevatten alleen wedstrijden en zaaldiensten uit de gekozen sporthal.') }}</p>
                </div>
            </div>
            <div class="grid gap-2.5 md:grid-cols-3">
                @foreach ([
                    ['action' => 'dayExcel', 'icon' => 'heroicon-o-table-cells', 'badge' => 'XLSX', 'title' => __('Dagplanning'), 'text' => __('Alle wedstrijden.'), 'tone' => 'success'],
                    ['action' => 'daySchedulePdf', 'icon' => 'heroicon-o-document-text', 'badge' => 'PDF', 'title' => __('Deelbaar dagschema'), 'text' => __('Printvriendelijk.'), 'tone' => 'danger'],
                    ['action' => 'dayScheduleJpg', 'icon' => 'heroicon-o-photo', 'badge' => 'JPG', 'title' => __('Dagschema afbeelding'), 'text' => __('Website en socials.'), 'tone' => 'info'],
                ] as $export)
                    <button type="button" wire:click="{{ $export['action'] }}" wire:loading.attr="disabled" wire:target="{{ $export['action'] }}" @disabled(! $venueId) class="group rounded-xl border border-gray-200 p-3 text-left transition hover:border-primary-300 hover:bg-primary-50/50 disabled:opacity-50 dark:border-white/10 dark:hover:border-primary-500/30 dark:hover:bg-primary-500/5">
                        <div class="mb-2 flex items-center justify-between">
                            <x-filament::icon :icon="$export['icon']" @class(['h-5 w-5', 'text-success-600' => $export['tone'] === 'success', 'text-danger-600' => $export['tone'] === 'danger', 'text-info-600' => $export['tone'] === 'info']) />
                            <span class="text-[.65rem] font-bold text-gray-500">{{ $export['badge'] }}</span>
                        </div>
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $export['title'] }}</p>
                        <p class="mt-0.5 text-xs text-gray-500">{{ $export['text'] }}</p>
                        <p class="mt-2 text-xs font-semibold text-primary-600 dark:text-primary-400" wire:loading.remove wire:target="{{ $export['action'] }}">{{ __('Download →') }}</p>
                        <p class="mt-2 text-xs text-gray-400" wire:loading wire:target="{{ $export['action'] }}">{{ __('Maken…') }}</p>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/5">
            <label class="grid gap-1.5">
                <span class="flex items-center justify-between gap-3">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('Voettekst') }}</span>
                    @if (trim($footerText) !== $defaultFooterText)
                        <button type="button" wire:click="resetFooterText" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">{{ __('Standaardtekst terugzetten') }}</button>
                    @endif
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">{{ __('Verschijnt onderaan het dagschema (PDF en afbeelding). Een aanpassing hier geldt alleen voor deze export; de standaard stel je in bij de plannerinstellingen.') }}</span>
                <textarea wire:model.live.debounce.500ms="footerText" rows="2" maxlength="255" class="mt-1 rounded-lg border-gray-300 bg-white text-sm dark:bg-gray-800"></textarea>
            </label>
            @error('footerText')<p class="mt-2 text-xs text-danger-600">{{ $message }}</p>@enderror
        </div>
    </x-filament::section>

    @if ($this->withDuties())
        <x-filament::section
            :heading="__('FTZ-taken per wedstrijddag')"
            :description="__('Maak één chronologisch Excel-overzicht voor alle wedstrijddagen binnen een gekozen periode.')"
            icon="heroicon-o-clipboard-document-list"
        >
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] md:items-end">
                <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">{{ __('Van') }}<input type="text" wire:model="dutiesFromDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" class="rounded-lg border-gray-300 dark:bg-gray-800">@error('dutiesFromDate')<span class="text-xs text-danger-600">{{ $message }}</span>@enderror</label>
                <label class="grid gap-1.5 text-sm font-medium text-gray-950 dark:text-white">{{ __('Tot en met') }}<input type="text" wire:model="dutiesToDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" class="rounded-lg border-gray-300 dark:bg-gray-800">@error('dutiesToDate')<span class="text-xs text-danger-600">{{ $message }}</span>@enderror</label>
                <x-filament::button wire:click="dutiesExcel" icon="heroicon-o-table-cells" wire:loading.attr="disabled" wire:target="dutiesExcel" color="success"><span wire:loading.remove wire:target="dutiesExcel">{{ __('FTZ Excel downloaden') }}</span><span wire:loading wire:target="dutiesExcel">{{ __('Excel maken…') }}</span></x-filament::button>
            </div>
            <div class="mt-4 flex items-start gap-2 border-t border-gray-200 pt-4 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400"><x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 shrink-0" /><p>{{ __('Iedere wedstrijdregel bevat het fluitende team, het tellende team en de zaalwacht die op dat tijdstip dienst heeft.') }}</p></div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
