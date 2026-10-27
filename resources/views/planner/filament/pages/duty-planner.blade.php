<x-filament-panels::page>
    <x-filament::section
        :heading="__('1. Voorstel maken')"
        :description="__('De planner controleert wedstrijden, uitwedstrijden, trainingen en teamregels voordat een taak wordt voorgesteld.')"
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

        <div class="vp-ftz-stats">
            @foreach ([
                ['label' => __('Wedstrijden'), 'count' => $summary['matches']],
                ['label' => __('Scheidsrechters'), 'count' => $summary['referees']],
                ['label' => __('Tellers'), 'count' => $summary['scorers']],
                ['label' => __('Zaaldiensten'), 'count' => $summary['hall_duties']],
                ['label' => __('Aandachtspunten'), 'count' => $summary['issues'], 'warn' => $summary['issues'] > 0],
            ] as $card)
                <div class="vp-card vp-ftz-stat">
                    <span @class(['vp-ftz-stat-value', 'is-warn' => $card['warn'] ?? false])>{{ $card['count'] }}</span>
                    <span class="vp-stat-label">{{ $card['label'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="vp-card vp-ftz-review">
            <div class="mr-auto">
                <h2 class="vp-ftz-review-title">{{ __('2. Voorstel beoordelen en toepassen') }}</h2>
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
                @php
                    $blockSummary = trans_choice(':count wedstrijd|:count wedstrijden', $counts['matches']).' · '
                        .__(':referee fluiten · :scorer tellen · :hall zaaldienst', ['referee' => $counts['referee'], 'scorer' => $counts['scorer'], 'hall' => $counts['hall_duties']]);
                @endphp
                <x-filament::section
                    :heading="$block['venue'] . ' · ' . \App\Planner\Support\DateFormat::withWeekday($block['date'])"
                    :description="$blockSummary"
                    collapsible
                    class="vp-ftz-block"
                    wire:key="duty-block-{{ $block['date'] }}-{{ $block['venue_id'] }}"
                >
                    <x-slot name="afterHeader">
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($counts['issues'] > 0)
                                <span class="vp-badge is-warn">{{ trans_choice(':count aandachtspunt|:count aandachtspunten', $counts['issues']) }}</span>
                            @elseif ($checkedAt)
                                <span class="vp-badge is-ok">{{ __('Gecontroleerd') }}</span>
                            @else
                                <span class="vp-badge is-muted">{{ __('Niet gecontroleerd') }}</span>
                            @endif
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
                        <table class="vp-table vp-ftz-table">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Tijd') }}</th>
                                    <th scope="col">{{ __('Type') }}</th>
                                    <th scope="col">{{ __('Veld') }}</th>
                                    <th scope="col">{{ __('Wedstrijd of dienst') }}</th>
                                    <th scope="col">{{ __('Fluiten') }}</th>
                                    <th scope="col">{{ __('Tellen') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($block['rows'] as $row)
                                    @if ($row['type'] === 'hall_duty')
                                        <tr class="vp-ftz-hall-row" wire:key="duty-hall-{{ $row['index'] }}">
                                            <td class="is-time">{{ $row['time'] }}</td>
                                            <td >
                                                <span class="vp-badge is-muted">{{ __('Zaaldienst') }}</span>
                                            </td>
                                            <td class="vp-muted">—</td>
                                            <td class="vp-muted">{{ __('Zaaldienst :venue', ['venue' => $block['venue']]) }}</td>
                                            <td class="vp-ftz-duty">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-1">
                                                        <x-planner::duty-team-court
                                                            :path="'draft.hall_duties.' . $row['index'] . '.team_code'"
                                                            :value="$row['team']['value']"
                                                            :options="$teamOptions"
                                                            :field-errors="$row['team']['errors']"
                                                            :field-warnings="$row['team']['warnings']"
                                                            :label="__('Team voor zaaldienst')"
                                                            :choice="'openTeamChoice(\'hall_duty\', ' . $row['index'] . ')'"
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
                                            <td class="vp-muted">—</td>
                                        </tr>
                                    @else
                                        @php
                                            $refereeAction = "lock({$row['index']}, 'referee')";
                                            $scorerAction = "lock({$row['index']}, 'scorer')";
                                        @endphp
                                        {{-- Index in the key: the actions and fields in this row refer to it. --}}
                                        <tr wire:key="duty-match-{{ $row['id'] }}-{{ $row['index'] }}">
                                            <td class="is-time">{{ $row['time'] }}<span class="block text-xs font-normal text-gray-400">{{ __('tot :time', ['time' => $this->displayEndTime($row['time'])]) }}</span></td>
                                            <td >
                                                <span class="vp-muted">{{ __('Wedstrijd') }}</span>
                                            </td>
                                            <td class="vp-muted">{{ $row['court'] ?? '—' }}</td>
                                            <td >
                                                <p class="vp-ftz-match">{{ $row['home'] }} <span class="vp-muted">–</span> {{ $row['away'] }}</p>
                                                <p class="vp-ftz-code">{{ $row['code'] }}</p>
                                            </td>
                                            <td class="vp-ftz-duty">
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
                                                            :choice="'openTeamChoice(\'match\', ' . $row['index'] . ', \'referee\')'"
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
                                            <td class="vp-ftz-duty">
                                                <div class="flex items-start gap-2">
                                                    <div class="flex-1">
                                                        <x-planner::duty-team-court
                                                            :path="'draft.matches.' . $row['index'] . '.scorer'"
                                                            :value="$row['scorer']['value']"
                                                            :options="$teamOptions"
                                                            :field-errors="$row['scorer']['errors']"
                                                            :field-warnings="$row['scorer']['warnings']"
                                                            :label="__('Teller voor :code', ['code' => $row['code']])"
                                                            :choice="'openTeamChoice(\'match\', ' . $row['index'] . ', \'scorer\')'"
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
        <div class="vp-card vp-ftz-empty">
            <x-filament::icon icon="heroicon-o-sparkles" class="vp-ftz-empty-icon" />
            <h2>{{ __('Nog geen FTZ-voorstel') }}</h2>
            <p>{{ __('Kies een planningversie en generatiemodus. De planner maakt daarna een voorstel dat je kunt aanpassen en controleren voordat het wordt toegepast.') }}</p>
        </div>
    @endif
    @include('planner::filament.pages.duty-planner.team-choice')
    <x-filament-actions::modals />
</x-filament-panels::page>
