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
            <label class="away-filter"><span>{{ __('Planningversie') }}</span><select wire:model.live="versionId">@foreach ($this->versions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            <label class="away-filter"><span>{{ __('Zoeken') }}</span><span class="away-search"><x-filament::icon icon="heroicon-o-magnifying-glass" class="h-5 w-5"/><input wire:model.live.debounce.250ms="search" type="search" placeholder="{{ __('Team, poule of code') }}"></span></label>
            <label class="away-filter"><span>{{ __('Status') }}</span><select wire:model.live="status"><option value="all">{{ __('Alle statussen') }}</option>@foreach ($statusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
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
                    <label>{{ __('Status') }}<select wire:model.live="editing.status"><option value="none">{{ __('Geen wijziging') }}</option><option value="in_progress">{{ __('Wijziging loopt') }}</option><option value="final">{{ __('Definitief doorvoeren') }}</option><option value="rejected">{{ __('Voorstel afwijzen') }}</option></select></label>
                    <label>{{ __('Nieuwe datum') }}<input wire:model="editing.date" type="text" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off"></label>
                    <label>{{ __('Nieuwe tijd') }}<input wire:model="editing.time" type="text" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off"></label>
                    <label>{{ __('Nieuwe sporthal') }}<input wire:model="editing.venue" placeholder="{{ __('Naam sporthal') }}"></label>
                    <label class="away-span-2">{{ __('Opmerkingen') }}<textarea wire:model="editing.notes" rows="3"></textarea></label>
                </div>
                @if ($this->conflicts[$editMatchId] ?? [])<div class="away-warning"><strong>{{ __('Let op') }}</strong>@foreach ($this->conflicts[$editMatchId] as $conflict)<span>{{ $conflict }}</span>@endforeach</div>@endif
                <div class="away-actions"><x-filament::button type="button" color="gray" wire:click="closeEdit">{{ __('Annuleren') }}</x-filament::button><x-filament::button type="submit">{{ __('Opslaan') }}</x-filament::button></div>
            </form>
        </div>
    @endif

    @push('styles')<style>
        .away-shell{padding:1.5rem;border-radius:18px;background:color-mix(in srgb,var(--planner-primary,#0ea5e9) 14%,#fff)}.dark .away-shell{background:#102d3a}.away-filters{display:grid;grid-template-columns:minmax(13rem,.8fr) minmax(17rem,1.4fr) minmax(13rem,.8fr);align-items:end;gap:.75rem;margin-bottom:1.25rem}.away-filter{display:grid;gap:.35rem;font-size:.75rem;font-weight:700;color:#334155}.dark .away-filter{color:#e2e8f0}.away-filters select,.away-search{height:44px;border:1px solid #cbd5e1;border-radius:11px;background:#fff;color:#0f172a}.away-filters select{width:100%;padding:0 .8rem}.away-search{display:flex;align-items:center;gap:.45rem;padding:0 .7rem;min-width:0}.away-search input{min-width:0;border:0;background:transparent;outline:0;width:100%;color:inherit}.away-table-wrap{overflow:auto;border:1px solid #d5e1e8;border-radius:14px;background:#fff;box-shadow:0 2px 5px #0f172a12}.dark .away-table-wrap,.dark .away-filters select,.dark .away-search{background:#111827;border-color:#475569;color:#f8fafc}.away-table{width:100%;min-width:1100px;border-collapse:collapse;text-align:left}.away-table th{padding:.75rem;font-size:.77rem;color:#526174}.away-table td{padding:.9rem .75rem;border-top:1px solid #d9e3e9;font-size:.8rem}.dark .away-table th{color:#94a3b8}.dark .away-table td{border-top-color:#334155}.away-row{cursor:pointer}.away-row:hover td{background:#f0f9ff}.dark .away-row:hover td{background:#0c4a6e}.away-row:focus-visible{outline:2px solid var(--planner-primary,#0ea5e9);outline-offset:-2px}.away-pagination{margin-top:1rem}.away-line{display:flex;align-items:center;gap:.35rem;white-space:nowrap}.away-line svg{width:15px;color:#64748b}.away-table small{color:#64748b}.dark .away-line svg,.dark .away-table small{color:#94a3b8}.away-status{display:inline-flex;padding:.27rem .65rem;border-radius:999px;background:#e8f4fa;font-size:.7rem;font-weight:750;white-space:nowrap}.away-status.is-in_progress,.away-status.is-in_discussion{background:#fef3c7;color:#a16207}.away-status.is-final{background:#dcfce7;color:#15803d}.away-status.is-cancelled{background:#fee2e2;color:#b91c1c}.dark .away-status{background:#1e3a4a;color:#bae6fd}.dark .away-status.is-in_progress,.dark .away-status.is-in_discussion{background:#422006;color:#fcd34d}.dark .away-status.is-final{background:#052e16;color:#86efac}.dark .away-status.is-cancelled{background:#450a0a;color:#fca5a5}.away-conflict{color:#b45309;font-size:.72rem;font-weight:700}.away-ok{color:#94a3b8;font-size:.72rem}.dark .away-conflict{color:#fdba74}.dark .away-ok{color:#64748b}.away-edit{width:34px;height:34px;padding:7px;border-radius:8px}.away-edit:hover{background:#e0f2fe}.dark .away-edit:hover{background:#0c4a6e}.away-empty{text-align:center;color:#64748b;padding:3rem!important}.dark .away-empty{color:#94a3b8}.away-modal-bg{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:1rem;background:#0f172a66}.away-modal{width:min(680px,100%);max-height:90vh;overflow:auto;padding:1.25rem;border-radius:16px;background:#fff;box-shadow:0 24px 70px #0f172a55}.dark .away-modal{background:#111827}.away-modal-head{display:flex;justify-content:space-between;gap:1rem}.away-modal-head h3{font-size:1.15rem;font-weight:800}.away-modal-head p{font-size:.75rem;color:#64748b}.dark .away-modal-head p{color:#94a3b8}.away-modal-head button{flex:none;font-size:1.8rem}.away-current{display:flex;justify-content:space-between;gap:.75rem;margin:1rem 0;padding:.75rem;border-radius:9px;background:#f0f9ff;font-size:.78rem}.dark .away-current{background:#0c4a6e}.away-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}.away-form-grid label{display:grid;gap:.3rem;font-size:.75rem;font-weight:700}.away-form-grid input,.away-form-grid select,.away-form-grid textarea{border:1px solid #cbd5e1;border-radius:8px;padding:.55rem;background:transparent}.dark .away-form-grid input,.dark .away-form-grid select,.dark .away-form-grid textarea{border-color:#475569;color:#f8fafc}.away-span-2{grid-column:span 2}.away-warning{display:grid;gap:.25rem;margin-top:1rem;padding:.75rem;border:1px solid #fcd34d;border-radius:8px;background:#fffbeb;color:#92400e;font-size:.75rem}.dark .away-warning{border-color:#a16207;background:#2a1c02;color:#fde68a}.away-actions{display:flex;justify-content:flex-end;gap:.6rem;margin-top:1rem}@media(max-width:900px){.away-filters{grid-template-columns:1fr}.away-form-grid{grid-template-columns:1fr}.away-span-2{grid-column:auto}}@media(max-width:520px){.away-shell{margin-inline:-.75rem;padding:1rem;border-radius:14px}.away-current{flex-direction:column}.away-actions{display:grid;grid-template-columns:1fr 1fr}.away-modal{padding:1rem}}
    </style>@endpush
    <x-filament-actions::modals />
</x-filament-panels::page>
