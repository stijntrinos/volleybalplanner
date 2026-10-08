<x-filament-panels::page>
    <x-filament::section
        :heading="__('1. FTZ-voorstel maken')"
        :description="__('De planner controleert wedstrijden, uitwedstrijden, trainingen en teamregels voordat een taak wordt voorgesteld.')"
        icon="heroicon-o-sparkles"
    >
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4 xl:items-end">
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Planningversie') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="versionId" :disabled="$this->versions->isEmpty()">
                        @forelse ($this->versions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @empty
                            <option value="">{{ __('Nog geen planningversies') }}</option>
                        @endforelse
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Van') }}</span>
                <x-filament::input.wrapper :valid="! $errors->has('fromDate')">
                    <x-filament::input type="text" wire:model="fromDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" />
                </x-filament::input.wrapper>
                @error('fromDate')<span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Tot en met') }}</span>
                <x-filament::input.wrapper :valid="! $errors->has('toDate')">
                    <x-filament::input type="text" wire:model="toDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" />
                </x-filament::input.wrapper>
                @error('toDate')<span class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Generatiemodus') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="mode">
                        <option value="fill">{{ __('Aanvullen — bestaande invulling behouden') }}</option>
                        <option value="redistribute">{{ __('Herverdelen — automatische invulling opnieuw berekenen') }}</option>
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
        <div class="mt-4 flex justify-end">
            <x-filament::button wire:click="generate" icon="heroicon-o-sparkles" wire:loading.attr="disabled" wire:target="generate">
                <span wire:loading.remove wire:target="generate">{{ __('Voorstel genereren') }}</span><span wire:loading wire:target="generate">{{ __('Voorstel maken…') }}</span>
            </x-filament::button>
        </div>
        <div class="mt-4 flex items-start gap-2 border-t border-gray-200 pt-4 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
            <x-filament::icon icon="heroicon-o-information-circle" class="h-4 w-4 shrink-0" />
            <p>{{ $mode === 'fill' ? __('Alle bestaande handmatige en automatische toewijzingen binnen de gekozen periode blijven staan; alleen lege plekken worden aangevuld.') : __('Handmatige toewijzingen blijven staan; eerder automatisch ingevulde taken binnen de gekozen periode worden opnieuw verdeeld.') }}</p>
        </div>
    </x-filament::section>

    @if ($draft)
        @php
            $summary = $this->summary;
            $teamOptions = $this->teamOptions;
        @endphp

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ([
                ['label' => __('Wedstrijden'), 'count' => $summary['matches'], 'icon' => 'heroicon-o-trophy'],
                ['label' => __('Scheidsrechters'), 'count' => $summary['referees'], 'icon' => 'heroicon-o-megaphone'],
                ['label' => __('Tellers'), 'count' => $summary['scorers'], 'icon' => 'heroicon-o-pencil-square'],
                ['label' => __('Zaaldiensten'), 'count' => $summary['hall_duties'], 'icon' => 'heroicon-o-key'],
                ['label' => __('Aandachtspunten'), 'count' => $summary['issues'], 'icon' => 'heroicon-o-exclamation-triangle'],
            ] as $card)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-center gap-3"><div class="rounded-lg bg-gray-100 p-2 text-gray-500 dark:bg-white/5"><x-filament::icon :icon="$card['icon']" class="h-5 w-5" /></div><div><p class="text-2xl font-semibold text-gray-950 dark:text-white">{{ $card['count'] }}</p><p class="text-xs text-gray-500">{{ $card['label'] }}</p></div></div>
                </div>
            @endforeach
        </div>

        <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="mr-auto">
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('2. Voorstel beoordelen en controleren') }}</p>
                @if ($checkedAt)
                    <p @class(['mt-0.5 text-xs font-medium', 'text-warning-600 dark:text-warning-400' => $summary['issues'] > 0, 'text-success-600 dark:text-success-400' => $summary['issues'] === 0])>
                        {{ __('Gecontroleerd :time', ['time' => $checkedAt]) }} · {{ trans_choice(':count aandachtspunt|:count aandachtspunten', $summary['issues']) }}
                    </p>
                @else
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ __('Wijzigingen na een controle moeten opnieuw worden gecontroleerd.') }}</p>
                @endif
            </div>
            <x-filament::button wire:click="check" color="gray" icon="heroicon-o-shield-check" wire:loading.attr="disabled" wire:target="check">
                <span wire:loading.remove wire:target="check">{{ __('Alles controleren') }}</span><span wire:loading wire:target="check">{{ __('Controleren…') }}</span>
            </x-filament::button>
            <x-filament::button wire:click="save" color="success" icon="heroicon-o-check" wire:loading.attr="disabled" wire:target="save" wire:confirm="{{ __('FTZ-concept toepassen op deze planningversie?') }}">
                <span wire:loading.remove wire:target="save">{{ __('Alles toepassen') }}</span><span wire:loading wire:target="save">{{ __('Toepassen…') }}</span>
            </x-filament::button>
        </div>

        <div class="space-y-5">
            @php
                $lastDate = null;
            @endphp
            @foreach ($this->blocks as $block)
                @php
                    $counts = $block['counts'];
                    $blockArguments = "'" . $block['date'] . "', " . ($block['venue_id'] ?? 'null');
                    $showDayLock = $lastDate !== $block['date'];
                    $lastDate = $block['date'];
                @endphp
                <x-filament::section
                    :heading="$block['venue']"
                    :description="\App\Planner\Support\DateFormat::withWeekday($block['date'])"
                    icon="heroicon-o-building-office-2"
                    collapsible
                    wire:key="duty-block-{{ $block['date'] }}-{{ $block['venue_id'] }}"
                >
                    <x-slot name="afterHeader">
                        <div class="flex flex-wrap items-center gap-2">
                            <p @class(['mr-2 text-xs', 'text-warning-600 dark:text-warning-400' => $counts['issues'] > 0, 'text-gray-500 dark:text-gray-400' => $counts['issues'] === 0])>
                                {{ trans_choice(':count wedstrijd|:count wedstrijden', $counts['matches']) }} ·
                                {{ __(':referee fluit · :scorer tel · :hall zaaldienst', ['referee' => $counts['referee'], 'scorer' => $counts['scorer'], 'hall' => $counts['hall_duties']]) }}
                                @if ($counts['issues'] > 0)
                                    · {{ trans_choice(':count aandachtspunt|:count aandachtspunten', $counts['issues']) }}
                                @endif
                            </p>
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-shield-check"
                                wire:click="checkBlock({{ $blockArguments }})" wire:loading.attr="disabled">
                                {{ __('Controleren') }}
                            </x-filament::button>
                            @if ($showDayLock)
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-lock-closed"
                                    wire:click="lockDay('{{ $block['date'] }}')" wire:loading.attr="disabled">
                                    {{ __('Dag vastzetten') }}
                                </x-filament::button>
                                @php
                                    $noHallDuty = $this->isHallDutyException($block['date']);
                                    $hallDutyColor = $noHallDuty ? 'warning' : 'gray';
                                    $hallDutyIcon = $noHallDuty ? 'heroicon-o-arrow-path' : 'heroicon-o-no-symbol';
                                    $hallDutyLabel = $noHallDuty ? __('Weer zaaldienst') : __('Geen zaaldienst');
                                @endphp
                                <x-filament::button size="xs" :color="$hallDutyColor" :icon="$hallDutyIcon"
                                    wire:click="toggleHallDutyDay('{{ $block['date'] }}')" wire:loading.attr="disabled">
                                    {{ $hallDutyLabel }}
                                </x-filament::button>
                            @endif
                            <x-filament::button size="xs" color="gray" icon="heroicon-o-sparkles"
                                wire:click="generateBlock({{ $blockArguments }})" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Dit blok opnieuw genereren? Niet-vastgezette toewijzingen worden overschreven.') }}">
                                {{ __('Opnieuw genereren') }}
                            </x-filament::button>
                            <x-filament::button size="xs" color="success" icon="heroicon-o-check"
                                wire:click="applyBlock({{ $blockArguments }})" wire:loading.attr="disabled"
                                wire:confirm="{{ __('Alleen dit blok toepassen op de planningversie?') }}">
                                {{ __('Doorvoeren') }}
                            </x-filament::button>
                        </div>
                    </x-slot>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[60rem] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10">
                                    <th class="px-3 py-3">{{ __('Tijd') }}</th>
                                    <th class="px-3 py-3">{{ __('Type') }}</th>
                                    <th class="px-3 py-3">{{ __('Veld') }}</th>
                                    <th class="px-3 py-3">{{ __('Wedstrijd / dienst') }}</th>
                                    <th class="px-3 py-3">{{ __('Fluiten / team') }}</th>
                                    <th class="px-3 py-3">{{ __('Tellen') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @foreach ($block['rows'] as $row)
                                    @if ($row['type'] === 'hall_duty')
                                        <tr class="bg-warning-50/60 dark:bg-warning-500/5" wire:key="duty-hall-{{ $row['index'] }}">
                                            <td class="px-3 py-3 align-top font-semibold text-gray-950 dark:text-white">{{ $row['time'] }}</td>
                                            <td class="px-3 py-3 align-top">
                                                <x-filament::badge color="warning" icon="heroicon-o-key" size="sm">{{ __('Zaaldienst') }}</x-filament::badge>
                                            </td>
                                            <td class="px-3 py-3 align-top text-gray-400">—</td>
                                            <td class="px-3 py-3 align-top italic text-gray-500 dark:text-gray-400">{{ __('Zaaldienst :venue', ['venue' => $block['venue']]) }}</td>
                                            <td class="w-72 px-3 py-3 align-top">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-1">
                                                        <x-planner::duty-team-court
                                                            :path="'draft.hall_duties.' . $row['index'] . '.team_code'"
                                                            :value="$row['team']['value']"
                                                            :options="$teamOptions"
                                                            :field-errors="$row['team']['errors']"
                                                            :field-warnings="$row['team']['warnings']"
                                                            :label="__('Team voor zaaldienst')"
                                                        />
                                                    </div>
                                                    <x-planner::duty-keep
                                                        :locked="$row['team']['locked']"
                                                        :origin="$row['team']['origin']"
                                                        :action="'lockHallDuty(' . $row['index'] . ')'"
                                                        :task="__('de zaaldienst')"
                                                    />
                                                </div>
                                            </td>
                                            <td class="px-3 py-3 align-top text-gray-400">—</td>
                                        </tr>
                                    @else
                                        @php
                                            $refereeAction = "lock({$row['index']}, 'referee')";
                                            $scorerAction = "lock({$row['index']}, 'scorer')";
                                        @endphp
                                        {{-- Index in the key: the actions and fields in this row refer to it. --}}
                                        <tr wire:key="duty-match-{{ $row['id'] }}-{{ $row['index'] }}">
                                            <td class="px-3 py-3 align-top font-semibold text-gray-950 dark:text-white">{{ $row['time'] }}</td>
                                            <td class="px-3 py-3 align-top">
                                                <x-filament::badge color="gray" size="sm">{{ __('Wedstrijd') }}</x-filament::badge>
                                            </td>
                                            <td class="px-3 py-3 align-top text-gray-500 dark:text-gray-400">{{ $row['court'] ?? '—' }}</td>
                                            <td class="px-3 py-3 align-top">
                                                <p class="font-medium text-gray-950 dark:text-white">{{ $row['home'] }} <span class="font-normal text-gray-400">–</span> {{ $row['away'] }}</p>
                                                <p class="mt-1 font-mono text-xs text-gray-400">{{ $row['code'] }}</p>
                                            </td>
                                            <td class="w-72 px-3 py-3 align-top">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-1">
                                                        <x-planner::duty-team-court
                                                            :path="'draft.matches.' . $row['index'] . '.referee'"
                                                            :value="$row['referee']['value']"
                                                            :options="$teamOptions"
                                                            :field-errors="$row['referee']['errors']"
                                                            :field-warnings="$row['referee']['warnings']"
                                                            :label="__('Scheidsrechter voor :code', ['code' => $row['code']])"
                                                            nevobo
                                                        />
                                                    </div>
                                                    <x-planner::duty-keep
                                                        :locked="$row['referee']['locked']"
                                                        :origin="$row['referee']['origin']"
                                                        :action="$refereeAction"
                                                        :task="__('de scheidsrechter')"
                                                    />
                                                </div>
                                            </td>
                                            <td class="w-72 px-3 py-3 align-top">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-1">
                                                        <x-planner::duty-team-court
                                                            :path="'draft.matches.' . $row['index'] . '.scorer'"
                                                            :value="$row['scorer']['value']"
                                                            :options="$teamOptions"
                                                            :field-errors="$row['scorer']['errors']"
                                                            :field-warnings="$row['scorer']['warnings']"
                                                            :label="__('Teller voor :code', ['code' => $row['code']])"
                                                        />
                                                    </div>
                                                    <x-planner::duty-keep
                                                        :locked="$row['scorer']['locked']"
                                                        :origin="$row['scorer']['origin']"
                                                        :action="$scorerAction"
                                                        :task="__('de teller')"
                                                    />
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @else
        <div class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/50 px-6 py-14 text-center dark:border-white/10 dark:bg-white/[.02]">
            <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600 dark:bg-primary-500/10 dark:text-primary-400"><x-filament::icon icon="heroicon-o-sparkles" class="h-7 w-7" /></div>
            <h2 class="font-semibold text-gray-950 dark:text-white">{{ __('Nog geen FTZ-voorstel') }}</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">{{ __('Kies een planningversie en generatiemodus. De planner maakt daarna een voorstel dat je kunt aanpassen en controleren voordat het wordt toegepast.') }}</p>
        </div>
    @endif
    <x-filament-actions::modals />
</x-filament-panels::page>
