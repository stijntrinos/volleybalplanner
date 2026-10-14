@if ($modalActivityId !== null)
    @php
        $activityEditable = $editable && auth()->user()?->can($modalActivityId === 0 ? 'Create:Activity' : 'Update:Activity');
    @endphp
    <div class="planner-assistant-backdrop" wire:click.self="closeActivity" wire:keydown.escape.window="closeActivity" role="dialog" aria-modal="true" aria-label="{{ __('Activiteit') }}">
        <form class="planner-match-modal" wire:submit="saveActivity">
            <div class="planner-assistant-header">
                <div>
                    <h2>{{ $modalActivityId === 0 ? __('Nieuwe activiteit') : ($activityEditable ? __('Activiteit bewerken') : __('Activiteit bekijken')) }}</h2>
                    <p>{{ __('Datum: :date', ['date' => \Carbon\CarbonImmutable::parse($date)->format('d-m-Y')]) }} · {{ __('op dit veld en in dit tijdvak kan geen wedstrijd worden ingepland') }}</p>
                </div>
                <button type="button" wire:click="closeActivity" aria-label="{{ __('Sluiten') }}">×</button>
            </div>
            {{-- Read-only (published version, read-only club or no rights): every field disabled. --}}
            <fieldset class="planner-match-fields" @disabled(! $activityEditable)>
            <label class="planner-match-notes">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Naam') }}</span>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model="modalActivityTitle" :placeholder="__('Bijv. vaardigheidstraining')" maxlength="255" />
                </x-filament::input.wrapper>
            </label>
            <div class="planner-match-form-grid">
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Sporthal') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="modalActivityVenueId">@foreach ($this->activityVenues as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Veld') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model="modalActivityCourtId"><option value="">{{ __('Hele sporthal') }}</option>@foreach ($this->activityCourts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Begintijd') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="modalActivityStartTime" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off" />
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Eindtijd') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model="modalActivityEndTime" inputmode="numeric" placeholder="HH:mm" pattern="(?:[01]\d|2[0-3]):[0-5]\d" autocomplete="off" />
                    </x-filament::input.wrapper>
                </label>
            </div>
            <label class="planner-match-notes">
                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Opmerkingen') }}</span>
                <x-filament::input.wrapper class="fi-fo-textarea">
                    <textarea wire:model="modalActivityNotes" rows="3"></textarea>
                </x-filament::input.wrapper>
            </label>
            </fieldset>
            @if ($errors->any())<div class="planner-form-errors">{{ $errors->first() }}</div>@endif
            <div class="planner-match-modal-actions">
                @if ($editable && $modalActivityId !== 0 && auth()->user()?->can('Delete:Activity'))
                    <button type="button" class="is-danger" wire:click="deleteActivity" wire:confirm="{{ __('Deze activiteit verwijderen? Het veld komt dan weer vrij.') }}">{{ __('Verwijderen') }}</button>
                @endif
                <span></span>
                <button type="button" wire:click="closeActivity">{{ $activityEditable ? __('Annuleren') : __('Sluiten') }}</button>
                @if ($activityEditable)<button type="submit" class="is-primary">{{ $modalActivityId === 0 ? __('Toevoegen') : __('Opslaan') }}</button>@endif
            </div>
        </form>
    </div>
@endif
