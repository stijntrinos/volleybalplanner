@if ($assistantMatchId)
    <div class="planner-assistant-backdrop" wire:click.self="closeAssistant" wire:keydown.escape.window="closeAssistant" role="dialog" aria-modal="true" aria-label="{{ __('Verplaatsingsassistent') }}">
        <aside class="planner-assistant">
            <div class="planner-assistant-header">
                <div><h2>{{ __('Verplaatsingsassistent') }}</h2><p>{{ __('Alleen volledig groene voorstellen worden getoond.') }}</p></div>
                <button type="button" wire:click="closeAssistant" aria-label="{{ __('Sluiten') }}">×</button>
            </div>
            @if ($editable)
                {{-- For when it is not known yet where to: the match stays put
                     and appears among the matches still to plan. --}}
                <div class="planner-mark-move">
                    <span>{{ __('Weet je nog niet waarheen?') }}</span>
                    <button type="button" wire:click="markToMove({{ $assistantMatchId }})">{{ __('Markeren als te verplaatsen') }}</button>
                </div>
            @endif
            <div class="planner-assistant-selects">
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Sporthal') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="assistantVenueId" wire:change="findProposals">@foreach ($this->assistantVenues as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
                <label>
                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Veld') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="assistantCourtId" wire:change="findProposals">@foreach ($this->assistantCourts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</x-filament::input.select>
                    </x-filament::input.wrapper>
                </label>
            </div>
            {{-- Choosing a moment yourself: the same check as the proposals, but
                 a red outcome shows up too, with the reason. --}}
            <div class="planner-own-slot">
                <strong>{{ __('Zelf een moment controleren') }}</strong>
                <div class="planner-own-slot-fields">
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Datum') }}</span>
                        <x-filament::input.wrapper :valid="! $errors->has('ownDate')">
                            <x-filament::input type="date" wire:model="ownDate" />
                        </x-filament::input.wrapper>
                    </label>
                    <label>
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Tijd') }}</span>
                        <x-filament::input.wrapper :valid="! $errors->has('ownTime')">
                            <x-filament::input type="time" wire:model="ownTime" step="300" />
                        </x-filament::input.wrapper>
                    </label>
                    <button type="button" wire:click="checkOwnSlot" wire:loading.attr="disabled" wire:target="checkOwnSlot">{{ __('Controleren') }}</button>
                </div>
                @error('ownDate')<small class="planner-own-slot-error">{{ $message }}</small>@enderror
                @error('ownTime')<small class="planner-own-slot-error">{{ $message }}</small>@enderror
                @if ($ownCheck)
                    @php
                        $outcome = $ownCheck['check']['result'];
                        $green = $outcome === \App\Planner\Services\AvailabilityChecker::RESULT_GREEN;
                    @endphp
                    <article @class(['planner-own-slot-result', 'is-'.$outcome])>
                        <div>
                            <strong>{{ __(':date om :time', ['date' => $dateFormat::withWeekday($ownCheck['date'], short: true), 'time' => $ownCheck['time']]) }}</strong>
                            <span>{{ match ($outcome) { 'green' => __('Beide teams kunnen, geen conflicten'), 'warning' => __('Kan, maar let op'), default => __('Dit moment geeft een conflict') } }}</span>
                        </div>
                        @if ($ownCheck['check']['reasons'])
                            <ul>
                                @foreach ($ownCheck['check']['reasons'] as $reason)
                                    <li>{{ $reason['message'] }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($editable)
                            <div class="planner-own-slot-actions">
                                <button type="button" wire:click="saveOwnSlot">{{ __('Bewaren') }}</button>
                                <button type="button" wire:click="useOwnSlot" @unless ($green) wire:confirm="{{ __('Dit moment geeft meldingen. Toch toepassen?') }}" @endunless>{{ $green ? __('Direct toepassen') : __('Toch toepassen') }}</button>
                            </div>
                        @endif
                    </article>
                @endif
            </div>
            @if ($saved = $this->savedProposal)
                <div class="planner-saved-proposal">
                    <div><strong>{{ __('Opgeslagen voorstel') }}</strong><span>{{ __(':date om :time', ['date' => $dateFormat::withWeekday($saved['date'], short: true), 'time' => $saved['time']]) }} · {{ $saved['venue'] }} {{ __('veld :court', ['court' => $saved['court']]) }}</span></div>
                    <button type="button" wire:click="deleteProposal" wire:confirm="{{ __('Dit opgeslagen voorstel verwijderen?') }}">{{ __('Verwijderen') }}</button>
                </div>
            @endif
            <div class="planner-assistant-results">
                @forelse ($proposals as $index => $proposal)
                    <article>
                        <div><strong>{{ $dateFormat::withWeekday($proposal['date'], short: true) }}</strong><span>{{ $proposal['time'] }} · {{ __('geen conflicten') }}</span></div>
                        <div><button type="button" wire:click="saveProposal({{ $index }})">{{ __('Bewaren') }}</button><button type="button" wire:click="useProposal({{ $index }})">{{ __('Direct toepassen') }}</button></div>
                    </article>
                @empty
                    <p>{{ __('Geen volledig gecontroleerde vrije momenten gevonden.') }}</p>
                @endforelse
            </div>
            @php $history = $this->moveHistory; @endphp
            <details class="planner-history">
                <summary>{{ __('Historie (:count)', ['count' => count($history)]) }}</summary>
                <div>
                    @forelse ($history as $item)
                        <article><strong>{{ $item['description'] }}</strong><small>{{ $item['moment'] }}</small></article>
                    @empty
                        <p>{{ __('Nog geen verplaatsingshistorie.') }}</p>
                    @endforelse
                </div>
            </details>
        </aside>
    </div>
@endif
