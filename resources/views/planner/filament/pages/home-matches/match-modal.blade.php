@if ($modalMatchId !== null && ($modalMatchId === 0 || $this->modalMatch))
    <div class="planner-assistant-backdrop" wire:click.self="closeMatch" wire:keydown.escape.window="closeMatch" role="dialog" aria-modal="true" aria-label="{{ __('Wedstrijd bewerken') }}">
        <form class="planner-match-modal" wire:submit="saveMatch">
            <div class="planner-assistant-header">
                <div>
                    <h2>{{ $modalMatchId === 0 ? __('Nieuwe wedstrijd') : __('Wedstrijd bewerken') }}</h2>
                    <p>{{ $modalMatchId === 0 ? __('Datum: :date', ['date' => $modalDate]) : $this->modalMatch->venue?->name.' · '.__('veld :court', ['court' => $this->modalMatch->court?->name]) }}</p>
                </div>
                <button type="button" wire:click="closeMatch" aria-label="{{ __('Sluiten') }}">×</button>
            </div>
            <div class="planner-match-form-grid">
                <label>{{ __('Sporthal') }}<select wire:model.live="modalVenueId">@foreach ($this->modalVenues as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label>{{ __('Veld') }}<select wire:model="modalCourtId" @disabled($modalSecondCourtId)>@foreach ($this->modalCourts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
            </div>
            <fieldset class="planner-duration"><legend>{{ __('Duur') }}</legend>@foreach ([105, 120, 150] as $duration)<button type="button" wire:click="$set('modalDuration', {{ $duration }})" @class(['is-active' => $modalDuration === $duration])>{{ __(':minutes min', ['minutes' => $duration]) }}</button>@endforeach</fieldset>
            <div class="planner-match-form-grid planner-match-form-grid-three">
                <label>{{ __('Datum') }}<input type="text" wire:model="modalDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off"></label>
                <label>{{ __('Begintijd') }}<input type="text" wire:model="modalTime" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off"></label>
                <label>{{ __('Wedstrijdcode') }}<input type="text" wire:model="modalMatchCode" @readonly($modalMatchId !== 0)></label>
            </div>
            <div class="planner-match-form-grid">
                <label>{{ __('Thuis') }}<input type="text" wire:model="modalHomeTeam" @readonly($modalMatchId !== 0)></label>
                <label>{{ __('Uit') }}<input type="text" wire:model="modalAwayTeam" @readonly($modalMatchId !== 0)></label>
                <label>{{ __('Fluiten') }}<select wire:model="modalReferee"><option value="">—</option><option value="Nevobo">Nevobo</option>@foreach ($this->modalOfficials as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></label>
                <label>{{ __('Tellen') }}<select wire:model="modalScorer"><option value="">—</option>@foreach ($this->modalOfficials as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</select></label>
            </div>
            <label class="planner-match-notes">{{ __('Opmerkingen') }}<textarea wire:model="modalNotes" rows="3"></textarea></label>
            <label class="planner-two-fields">
                <input type="checkbox" @checked($modalSecondCourtId) x-on:change="$wire.setTwoCourts($event.target.checked)">
                <span>{{ __('Gebruikt twee velden') }}<small>{{ __('De wedstrijd beslaat veld 1 en 2') }}</small></span>
            </label>
            @if ($errors->any())<div class="planner-form-errors">{{ $errors->first() }}</div>@endif
            <div class="planner-match-modal-actions">
                @if ($editable && $modalMatchId !== 0)
                    <button type="button" class="is-danger" wire:click="deleteMatch" wire:confirm="{{ __('Deze wedstrijd definitief uit deze conceptplanning verwijderen?') }}">{{ __('Verwijderen') }}</button>
                @endif
                <span></span>
                <button type="button" wire:click="closeMatch">{{ __('Annuleren') }}</button>
                @if ($editable)<button type="submit" class="is-primary">{{ $modalMatchId === 0 ? __('Toevoegen') : __('Opslaan') }}</button>@endif
            </div>
        </form>
    </div>
@endif
