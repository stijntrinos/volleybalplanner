<div class="planner-toolbar">
    <div class="planner-toolbar-primary">
        <div class="planner-date-navigation">
            <button type="button" wire:click="navigate(-1)" aria-label="{{ __('Vorige wedstrijddag') }}">‹</button>
            <div class="planner-current-date">
                <span>{{ \Carbon\CarbonImmutable::parse($date)->locale('nl')->translatedFormat('l') }}</span>
                <input type="text" value="{{ $dateInput }}" wire:change="selectDate($event.target.value)" wire:key="planner-date-{{ $date }}" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" aria-label="{{ __('Datum') }}">
                {{-- Typing stays possible in the own notation; the button opens the browser calendar. --}}
                <button
                    type="button"
                    class="planner-date-picker"
                    aria-label="{{ __('Kies een datum in de kalender') }}"
                    title="{{ __('Kies een datum in de kalender') }}"
                    x-on:click="$refs.plannerCalendar.showPicker ? $refs.plannerCalendar.showPicker() : $refs.plannerCalendar.focus()"
                >
                    <x-filament::icon icon="heroicon-o-calendar-days" class="planner-icon" />
                </button>
                <input
                    type="date"
                    x-ref="plannerCalendar"
                    class="planner-date-native"
                    value="{{ $date }}"
                    wire:key="planner-calendar-{{ $date }}"
                    wire:change="selectDate($event.target.value)"
                    tabindex="-1"
                    aria-hidden="true"
                >
            </div>
            <button type="button" wire:click="navigate(1)" aria-label="{{ __('Volgende wedstrijddag') }}">›</button>
        </div>
        <button type="button" class="planner-today" wire:click="goToToday">{{ __('Vandaag') }}</button>
        <x-filament::input.wrapper class="planner-version">
            <x-filament::input.select wire:model.live="versionId" class="h-11" aria-label="{{ __('Planningversie') }}" :disabled="$this->versions->isEmpty()">
                @forelse ($this->versions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @empty
                    <option value="">{{ __('Nog geen planningversies') }}</option>
                @endforelse
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>
    <div class="planner-toolbar-secondary">
        <button
            type="button"
            class="planner-search-trigger"
            @click="searchOpen = true; $nextTick(() => $refs.plannerSearch?.focus())"
            aria-label="{{ __('Wedstrijd zoeken') }}"
            title="{{ __('Wedstrijd zoeken (⌘K / Ctrl+K)') }}"
        >
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="planner-icon" />
            @if ($search !== '')<span class="planner-search-active" aria-label="{{ __('Zoekfilter actief') }}"></span>@endif
        </button>
        <button
            type="button"
            @class(['planner-unplanned-trigger', 'is-active' => $showUnplanned])
            wire:click="$toggle('showUnplanned')"
            aria-label="{{ $showUnplanned ? __('Nog te plannen wedstrijden verbergen') : __('Nog te plannen wedstrijden tonen') }}"
            aria-expanded="{{ $showUnplanned ? 'true' : 'false' }}"
            title="{{ __('Nog te plannen wedstrijden') }}"
        >
            <x-filament::icon icon="heroicon-o-rectangle-stack" class="planner-icon" />
            @if (count($day['to_plan'] ?? []))
                <span class="planner-unplanned-badge">{{ count($day['to_plan'] ?? []) }}</span>
            @endif
        </button>
        @if ($editable)
            <button type="button" class="planner-add-match" wire:click="openNewMatch" title="{{ __('Wedstrijd toevoegen') }}">
                <x-filament::icon icon="heroicon-o-plus" class="planner-icon-sm" />
                <span>{{ __('Wedstrijd toevoegen') }}</span>
            </button>
        @endif
        @if ($editable && auth()->user()?->can('Create:Activity'))
            <button type="button" class="planner-settings-trigger" wire:click="openNewActivity" aria-label="{{ __('Activiteit toevoegen') }}" title="{{ __('Activiteit toevoegen, bijvoorbeeld om een veld te blokkeren') }}">
                <x-filament::icon icon="heroicon-o-no-symbol" class="planner-icon" />
            </button>
        @endif
        @if ($versionId)
            <div class="planner-export" x-data="{ open: false }" @click.outside="open = false">
                <button type="button" class="planner-settings-trigger" @click="open = ! open" :aria-expanded="open" aria-label="{{ __('Dagschema exporteren') }}" title="{{ __('Dagschema exporteren') }}">
                    <x-filament::icon icon="heroicon-o-photo" class="planner-icon" />
                </button>
                <div class="planner-settings-menu" x-cloak x-show="open" x-transition.origin.top.right>
                    <div class="planner-settings-title">{{ __('Dagschema exporteren') }}</div>
                    <button type="button" wire:click="exportDayJpg" @click="open = false">{{ __('JPG downloaden') }}</button>
                    <button type="button" wire:click="exportDayPdf" @click="open = false">{{ __('PDF downloaden') }}</button>
                </div>
            </div>
        @endif
    </div>
</div>

<div
    x-cloak
    x-show="searchOpen"
    x-transition.opacity
    class="planner-search-backdrop"
    role="dialog"
    aria-modal="true"
    aria-labelledby="planner-search-title"
    @click.self="searchOpen = false"
>
    <div class="planner-search-modal" x-transition.scale.95.origin.top>
        <div class="planner-search-modal-header">
            <div>
                <h2 id="planner-search-title">{{ __('Wedstrijd zoeken') }}</h2>
                <p>{{ __('Zoek op teamnaam of wedstrijdcode') }}</p>
            </div>
            <button type="button" @click="searchOpen = false" aria-label="{{ __('Zoeken sluiten') }}">×</button>
        </div>
        <div class="planner-search-input">
            <x-filament::icon icon="heroicon-o-magnifying-glass" class="planner-icon" />
            <input
                x-ref="plannerSearch"
                type="search"
                wire:model.live.debounce.250ms="search"
                placeholder="{{ __('Bijvoorbeeld D1, H3 of wedstrijdcode') }}"
                autocomplete="off"
                aria-label="{{ __('Zoek op teamnaam of wedstrijdcode') }}"
            >
            @if ($search !== '')
                <button type="button" wire:click="$set('search', '')" aria-label="{{ __('Zoekopdracht wissen') }}">{{ __('Wissen') }}</button>
            @endif
        </div>
        <div class="planner-search-results">
            @php $results = $this->searchResults; @endphp
            @if (mb_strlen(trim($search)) < 2)
                <div class="planner-search-empty">{{ __('Typ minimaal twee tekens om wedstrijden te zoeken.') }}</div>
            @elseif (empty($results))
                <div class="planner-search-empty">{{ __('Geen wedstrijden gevonden voor “:search”.', ['search' => $search]) }}</div>
            @else
                @foreach ($results as $result)
                    <button type="button" wire:click="selectSearchResult({{ $result['id'] }})" class="planner-search-result">
                        <span class="planner-search-result-date">
                            <strong>{{ $result['date_label'] }}</strong>
                            <small>{{ $result['time'] }}</small>
                        </span>
                        <span class="planner-search-result-match">
                            <strong>{{ $result['home_team'] }} – {{ $result['away_team'] }}</strong>
                            <small>#{{ $result['code'] }}@if ($result['venue']) · {{ $result['venue'] }}@endif @if ($result['court'])· {{ __('Veld :court', ['court' => $result['court']]) }}@endif</small>
                        </span>
                        <x-filament::icon icon="heroicon-o-chevron-right" class="planner-icon-sm" />
                    </button>
                @endforeach
            @endif
        </div>
        <div class="planner-search-modal-footer">
            <span><kbd>Esc</kbd> {{ __('sluiten') }}</span>
            @if (count($results) === 25)<span>{{ __('Maximaal 25 resultaten') }}</span>@endif
        </div>
    </div>
</div>
