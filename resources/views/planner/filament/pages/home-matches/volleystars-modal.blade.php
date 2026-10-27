@if ($modalVolleystarsId && $this->modalVolleystarsBlock)
    <div class="planner-assistant-backdrop" wire:click.self="closeVolleystars" wire:keydown.escape.window="closeVolleystars" role="dialog" aria-modal="true" aria-label="{{ __('VolleyStars-taken') }}">
        <form class="planner-match-modal planner-volleystars-modal" wire:submit="saveVolleystars">
            <div class="planner-assistant-header">
                <div>
                    <h2>{{ __('VolleyStars direct koppelen') }}</h2>
                    <p>{{ $this->modalVolleystarsBlock->date?->format('d-m-Y') }} · {{ $this->modalVolleystarsBlock->venue?->name }} · {{ __('hele sporthal') }}</p>
                </div>
                <button type="button" wire:click="closeVolleystars" aria-label="{{ __('Sluiten') }}">×</button>
            </div>
            <p class="planner-volleystars-intro">{{ __('Koppel per taak maximaal drie teams. Hetzelfde team mag meerdere plekken invullen; de taak geldt voor alle velden in deze sporthal. De teams staan in de volgorde van de FTZ-planning, met hun stand.') }}</p>
            <div class="planner-volleystars-tasks">
                @foreach ($this->volleystarsRoles as $role => $label)
                    <fieldset class="planner-volleystars-task" wire:key="volleystars-{{ $role }}">
                        <legend>{{ $label }}</legend>
                        <div class="planner-volleystars-times">
                            <label>
                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Van') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input type="time" step="900" wire:model="modalVolleystarsTimes.{{ $role }}.start" autocomplete="off" />
                                </x-filament::input.wrapper>
                            </label>
                            <span>–</span>
                            <label>
                                <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Tot') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input type="time" step="900" wire:model="modalVolleystarsTimes.{{ $role }}.end" autocomplete="off" />
                                </x-filament::input.wrapper>
                            </label>
                        </div>
                        <div class="planner-volleystars-team-grid">
                            @for ($position = 0; $position < 3; $position++)
                                <label>
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">{{ __('Team :number', ['number' => $position + 1]) }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select wire:model="modalVolleystarsTeams.{{ $role }}.{{ $position }}">
                                            <option value="">{{ __('Niet gekoppeld') }}</option>
                                            @forelse ($volleystarsChoice[$role] ?? [] as $group => $teams)
                                                <optgroup label="{{ $group }}">
                                                    @foreach ($teams as $teamId => $teamLabel)
                                                        <option value="{{ $teamId }}">{{ $teamLabel }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @empty
                                                @foreach ($this->volleystarsTeams as $teamId => $teamLabel)
                                                    <option value="{{ $teamId }}">{{ $teamLabel }}</option>
                                                @endforeach
                                            @endforelse
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                            @endfor
                        </div>
                        @error("modalVolleystarsTeams.{$role}")<p class="planner-form-errors">{{ $message }}</p>@enderror
                        @error("modalVolleystarsTimes.{$role}.end")<p class="planner-form-errors">{{ $message }}</p>@enderror
                    </fieldset>
                @endforeach
            </div>
            @error('modalVolleystarsTeams')<div class="planner-form-errors">{{ $message }}</div>@enderror
            <div class="planner-match-modal-actions">
                <span></span><span></span>
                <button type="button" wire:click="closeVolleystars">{{ __('Annuleren') }}</button>
                @if ($editable)
                    <button type="submit" class="is-primary">{{ __('Teams opslaan') }}</button>
                @endif
            </div>
        </form>
    </div>
@endif
