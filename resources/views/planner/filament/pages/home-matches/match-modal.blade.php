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
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Sporthal') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="modalVenueId">@foreach ($this->modalVenues as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Veld') }}</span>
                    <x-filament::input.wrapper :disabled="(bool) $modalSecondCourtId">
                        <x-filament::input.select wire:model="modalCourtId" :disabled="(bool) $modalSecondCourtId">@foreach ($this->modalCourts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
            <fieldset class="planner-duration"><legend>{{ __('Duur') }}</legend>@foreach ([105, 120, 150] as $duration)<button type="button" wire:click="$set('modalDuration', {{ $duration }})" @class(['is-active' => $modalDuration === $duration])>{{ __(':minutes min', ['minutes' => $duration]) }}</button>@endforeach</fieldset>
            <div class="planner-match-form-grid planner-match-form-grid-three">
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Datum') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="modalDate" inputmode="numeric" placeholder="dd-MM-jjjj" pattern="\d{2}-\d{2}-\d{4}" autocomplete="off" />
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Begintijd') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="modalTime" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off" />
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Wedstrijdcode') }}</span>
                    <x-filament::input.wrapper :disabled="$modalMatchId !== 0">
                        <x-filament::input type="text" wire:model="modalMatchCode" :readonly="$modalMatchId !== 0" />
                    </x-filament::input.wrapper>
                </label>
            </div>
            <div class="planner-match-form-grid">
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Thuis') }}</span>
                    <x-filament::input.wrapper :disabled="$modalMatchId !== 0">
                        <x-filament::input type="text" wire:model="modalHomeTeam" :readonly="$modalMatchId !== 0" />
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Uit') }}</span>
                    <x-filament::input.wrapper :disabled="$modalMatchId !== 0">
                        <x-filament::input type="text" wire:model="modalAwayTeam" :readonly="$modalMatchId !== 0" />
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Fluiten') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="modalReferee"><option value="">—</option><option value="Nevobo">Nevobo</option>@foreach ($this->modalOfficials as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Tellen') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="modalScorer"><option value="">—</option>@foreach ($this->modalOfficials as $code)<option value="{{ $code }}">{{ $code }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
            <label class="planner-match-notes">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Opmerkingen') }}</span>
                <x-filament::input.wrapper class="fi-fo-textarea">
                    <textarea wire:model="modalNotes" rows="3"></textarea>
                </x-filament::input.wrapper>
            </label>
            <label class="planner-two-fields">
                <x-filament::input.checkbox :checked="(bool) $modalSecondCourtId" x-on:change="$wire.setTwoCourts($event.target.checked)" />
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Gebruikt twee velden') }}<small>{{ __('De wedstrijd beslaat veld 1 en 2') }}</small></span>
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
