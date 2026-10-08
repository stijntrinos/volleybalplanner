<x-filament-panels::page>
    @php
        $statusLabels = [
            'none' => __('Geen wijziging'),
            'in_progress' => __('Wijziging loopt'),
            'in_discussion' => __('In overleg'),
            'final' => __('Definitief gewijzigd'),
            'cancelled' => __('Afgelast'),
        ];
    @endphp
    <div class="away-shell">
        <div class="away-filters">
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
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Zoeken') }}</span>
                <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                    <x-filament::input wire:model.live.debounce.250ms="search" type="search" placeholder="{{ __('Team, poule of code') }}" />
                </x-filament::input.wrapper>
            </label>
            <label class="grid gap-2">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Status') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select wire:model.live="status">
                        <option value="all">{{ __('Alle statussen') }}</option>
                        @foreach ($statusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>

        <div class="away-table-wrap">
            <table class="away-table">
                <thead><tr><th>{{ __('Thuis') }}</th><th>{{ __('Uit') }}</th><th>{{ __('Code') }}</th><th>{{ __('Actueel') }}</th><th>{{ __('Voorstel') }}</th><th>{{ __('Status') }}</th><th>{{ __('Conflicten') }}</th><th></th></tr></thead>
                <tbody>
                @forelse ($this->matches as $match)
                    @php
                        $matchConflicts = $this->conflicts[$match->id] ?? [];
                        $status = $match->away_change_status?->value ?? 'none';
                    @endphp
                    <tr class="away-row" wire:click="edit({{ $match->id }})" tabindex="0" role="button" wire:key="away-{{ $match->id }}" x-on:keydown.enter="$wire.edit({{ $match->id }})" x-on:keydown.space.prevent="$wire.edit({{ $match->id }})">
                        <td><strong>{{ $match->home_team_name }}</strong></td>
                        <td>{{ $match->away_team_name }}</td>
                        <td>{{ $match->match_code }}</td>
                        <td><span class="away-line"><x-filament::icon icon="heroicon-o-calendar-days"/>{{ $match->date?->format('d-m-Y') ?: '—' }} {{ substr($match->time ?? '', 0, 5) }}</span><span class="away-line"><x-filament::icon icon="heroicon-o-map-pin"/>{{ $match->venue?->name ?: $match->original_venue ?: __('Onbekend') }}</span></td>
                        <td>
                            @if ($match->away_new_date || $match->away_new_time || $match->away_new_venue)
                                <span class="away-line">{{ $match->away_new_date?->format('d-m-Y') ?: $match->date?->format('d-m-Y') }} {{ substr($match->away_new_time ?: $match->time, 0, 5) }}</span><small>{{ $match->away_new_venue ?: __('Zelfde sporthal') }}</small>
                            @else — @endif
                        </td>
                        <td><span class="away-status is-{{ $status }}">{{ $statusLabels[$status] ?? ucfirst($status) }}</span></td>
                        <td>@if ($matchConflicts)<span class="away-conflict" title="{{ implode(' ', $matchConflicts) }}">{{ trans_choice(':count conflict|:count conflicten', count($matchConflicts)) }}</span>@else<span class="away-ok">{{ __('Geen') }}</span>@endif</td>
                        <td><button type="button" class="away-edit" wire:click.stop="edit({{ $match->id }})" aria-label="{{ __('Wedstrijd bewerken') }}" tabindex="-1"><x-filament::icon icon="heroicon-o-pencil"/></button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="away-empty">{{ __('Geen uitwedstrijden voor de gekozen filters.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->matches->hasPages() || $this->matches->total() > 25)
            <div class="away-pagination">
                <x-filament::pagination
                    :paginator="$this->matches"
                    :page-options="[25, 50, 100]"
                    current-page-option-property="perPage"
                />
            </div>
        @endif
    </div>

    @if ($editMatchId)
        @php
            $active = $this->matches->firstWhere('id', $editMatchId)
                ?? \App\Planner\Models\FixtureMatch::find($editMatchId);
        @endphp
        <div class="away-modal-bg" wire:click.self="closeEdit">
            <form class="away-modal" wire:submit="save">
                <div class="away-modal-head"><div><h3>{{ __('Uitwedstrijd wijzigen') }}</h3><p>{{ $active?->home_team_name }} – {{ $active?->away_team_name }} · {{ $active?->match_code }}</p></div><button type="button" wire:click="closeEdit" aria-label="{{ __('Venster sluiten') }}">×</button></div>
                <div class="away-current"><strong>{{ __('Huidige planning') }}</strong><span>{{ $active?->date?->format('d-m-Y') }} {{ substr($active?->time ?? '', 0, 5) }} · {{ $active?->venue?->name ?: $active?->original_venue }}</span></div>
                <div class="away-form-grid">
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Status') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="editing.status"><option value="none">{{ __('Geen wijziging') }}</option><option value="in_progress">{{ __('Wijziging loopt') }}</option><option value="final">{{ __('Definitief doorvoeren') }}</option><option value="rejected">{{ __('Voorstel afwijzen') }}</option></x-filament::input.select>
                        </x-filament::input.wrapper>
                    </label>
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Nieuwe datum') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="editing.date" type="text" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" />
                        </x-filament::input.wrapper>
                    </label>
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Nieuwe tijd') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input wire:model="editing.time" type="text" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off" />
                        </x-filament::input.wrapper>
                    </label>
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Nieuwe sporthal') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="editing.venue" placeholder="{{ __('Naam sporthal') }}" />
                        </x-filament::input.wrapper>
                    </label>
                    <label class="away-span-2">
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Opmerkingen') }}</span>
                        <x-filament::input.wrapper class="fi-fo-textarea">
                            <textarea wire:model="editing.notes" rows="3"></textarea>
                        </x-filament::input.wrapper>
                    </label>
                </div>
                @if ($this->conflicts[$editMatchId] ?? [])<div class="away-warning"><strong>{{ __('Let op') }}</strong>@foreach ($this->conflicts[$editMatchId] as $conflict)<span>{{ $conflict }}</span>@endforeach</div>@endif
                <div class="away-actions"><x-filament::button type="button" color="gray" wire:click="closeEdit">{{ __('Annuleren') }}</x-filament::button><x-filament::button type="submit">{{ __('Opslaan') }}</x-filament::button></div>
            </form>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
