@php
    use App\Planner\Enums\ContactPreference;
    use App\Planner\Support\DateFormat;

    // One place for the field styling: the same border, spacing and focus
    // ring as the other public forms.
    $field = 'w-full border border-gray-300 bg-white px-4 py-2.5 text-gray-900 placeholder:text-gray-400';
    $label = 'mb-1 block text-sm font-semibold text-gray-800';
    $error = 'mt-1 block text-sm text-red-600';
@endphp

<div class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-16">
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ __('Wedstrijd verzetten') }}</h1>
        <p class="mt-2 text-gray-600">
            {{ __('Vul dit formulier in als je wedstrijd niet door kan gaan op het geplande moment. Wedstrijdzaken beoordeelt je verzoek en laat je weten wat er mogelijk is.') }}
        </p>
    </header>

    @if (! $open)
        <div class="border border-amber-200 bg-amber-50 p-6">
            <h2 class="text-lg font-semibold text-amber-900">{{ __('Het formulier is nu gesloten') }}</h2>
            <p class="mt-2 text-amber-800">
                {{ __('Op dit moment nemen we geen verzoeken aan. Neem contact op met wedstrijdzaken als je wedstrijd echt niet door kan gaan.') }}
            </p>
        </div>
    @elseif ($submitted)
        <div class="border border-green-200 bg-green-50 p-6">
            <h2 class="text-lg font-semibold text-green-900">{{ __('Je verzoek is doorgegeven') }}</h2>
            <p class="mt-2 text-green-800">
                {{ __('Wedstrijdzaken ziet je aanvraag in de planner staan en neemt contact met je op') }}
                {{ $contactPreference === ContactPreference::Whatsapp->value ? __('via WhatsApp') : __('via het e-mailadres dat je hebt ingevuld') }}.
                {{ __('Verzetten lukt niet altijd, dus houd rekening met de oorspronkelijke datum tot je iets hoort.') }}
            </p>
            <button type="button" wire:click="submitAnother" class="planner-text-primary mt-4 font-semibold hover:underline">
                {{ __('Nog een wedstrijd doorgeven') }}
            </button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-7">
            <div>
                <label for="request-team" class="{{ $label }}">{{ __('Je team') }}</label>
                <select id="request-team" wire:model.live="teamId" class="{{ $field }}">
                    <option value="">{{ __('- kies je team -') }}</option>
                    {{-- Code first: a team name often carries a sponsor, and on
                         the name alone you do not find "DS 1" back. --}}
                    @foreach ($this->teams as $team)
                        <option value="{{ $team->id }}">{{ trim($team->code.' - '.$team->name, ' -') }}</option>
                    @endforeach
                </select>
                @error('teamId')<span class="{{ $error }}">{{ $message }}</span>@enderror
                @if ($this->teams->isEmpty())
                    <span class="mt-1 block text-sm text-gray-500">{{ __('Er staan op dit moment geen komende wedstrijden in de planning.') }}</span>
                @endif
            </div>

            @if ($teamId)
                <fieldset>
                    <legend class="{{ $label }}">{{ __('Welke wedstrijd?') }}</legend>
                    <div class="mt-1 space-y-2">
                        @forelse ($this->matches as $match)
                            <label class="flex cursor-pointer items-start gap-3 border p-4 transition {{ (int) $matchId === $match->id ? 'planner-border-primary planner-tint-primary' : 'border-gray-300 bg-white hover:border-gray-400' }}">
                                <input type="radio" name="match" value="{{ $match->id }}" wire:model.live="matchId" class="mt-1">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-gray-900">{{ $match->home_team_name }} - {{ $match->away_team_name }}</span>
                                    <span class="mt-0.5 block text-sm text-gray-600">
                                        {{ DateFormat::withWeekday($match->date) }}
                                        @if ($match->time) {{ __('om :time', ['time' => DateFormat::time($match->time)]) }} @endif
                                        @if ($match->venue) · {{ $match->venue->name }} @endif
                                    </span>
                                </span>
                            </label>
                        @empty
                            <span class="block text-sm text-gray-500">{{ __('Voor dit team staan er geen komende wedstrijden gepland.') }}</span>
                        @endforelse
                    </div>
                    @error('matchId')<span class="{{ $error }}">{{ $message }}</span>@enderror
                </fieldset>
            @endif

            @if ($matchId)
                @include('planner::livewire.partials.request-reason', ['prefix' => 'request', 'placeholder' => __('Bijvoorbeeld: welke data zouden wél kunnen?')])

                <fieldset>
                    <legend class="{{ $label }}">{{ __('Nieuwe datum voorstellen') }} <span class="font-normal text-gray-500">({{ __('optioneel') }})</span></legend>
                    <p class="mb-2 text-sm text-gray-600">{{ __('Weet je al wanneer het wel kan? Wedstrijdzaken kijkt dan eerst naar dat moment.') }}</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="request-proposed-date" class="sr-only">{{ __('Voorgestelde datum') }}</label>
                            <input id="request-proposed-date" type="date" wire:model="proposedDate" min="{{ now()->toDateString() }}" class="{{ $field }}">
                            @error('proposedDate')<span class="{{ $error }}">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <label for="request-proposed-time" class="sr-only">{{ __('Voorgestelde tijd') }}</label>
                            <input id="request-proposed-time" type="time" wire:model="proposedTime" step="300" class="{{ $field }}">
                            @error('proposedTime')<span class="{{ $error }}">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </fieldset>

                @include('planner::livewire.partials.requester', ['prefix' => 'request'])
            @endif
        </form>
    @endif
</div>
