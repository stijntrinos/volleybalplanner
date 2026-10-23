@php
    use App\Planner\Support\DateFormat;

    $field = 'w-full border border-gray-300 bg-white px-4 py-2.5 text-gray-900 placeholder:text-gray-400';
    $label = 'mb-1 block text-sm font-semibold text-gray-800';
    $error = 'mt-1 block text-sm text-red-600';
@endphp

<div class="mx-auto w-full max-w-3xl px-4 py-10 sm:py-16">
    <header class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">{{ __('FTZ-dienst wijzigen') }}</h1>
        <p class="mt-2 text-gray-600">
            {{ __('Kies je team en de toegewezen fluit-, tel- of zaaldienst waarvoor je een wijziging wilt aanvragen. Wedstrijdzaken beoordeelt je verzoek en neemt contact met je op.') }}
        </p>
    </header>

    @if (! $open)
        <div class="border border-amber-200 bg-amber-50 p-6">
            <h2 class="text-lg font-semibold text-amber-900">{{ __('Het formulier is nu gesloten') }}</h2>
            <p class="mt-2 text-amber-800">{{ __('Op dit moment nemen we geen wijzigingsverzoeken aan. Neem bij spoed contact op met wedstrijdzaken.') }}</p>
        </div>
    @elseif ($submitted)
        <div class="border border-green-200 bg-green-50 p-6">
            <h2 class="text-lg font-semibold text-green-900">{{ __('Je verzoek is doorgegeven') }}</h2>
            <p class="mt-2 text-green-800">
                {{ __('Wedstrijdzaken beoordeelt je aanvraag en neemt contact op via het ingevulde e-mailadres. De bestaande FTZ-indeling blijft gelden totdat je bevestiging van een wijziging hebt ontvangen.') }}
            </p>
            <button type="button" wire:click="submitAnother" class="planner-text-primary mt-4 font-semibold hover:underline">{{ __('Nog een FTZ-dienst doorgeven') }}</button>
        </div>
    @else
        <form wire:submit="submit" class="space-y-7">
            <div>
                <label for="duty-request-team" class="{{ $label }}">{{ __('Je team') }}</label>
                <select id="duty-request-team" wire:model.live="teamId" class="{{ $field }}">
                    <option value="">{{ __('- kies je team -') }}</option>
                    @foreach ($this->teams as $team)
                        <option value="{{ $team->id }}">{{ trim($team->code.' - '.$team->name, ' -') }}</option>
                    @endforeach
                </select>
                @error('teamId')<span class="{{ $error }}">{{ $message }}</span>@enderror
                @if ($this->teams->isEmpty())
                    <span class="mt-1 block text-sm text-gray-500">{{ __('Er zijn op dit moment geen komende FTZ-diensten toegewezen.') }}</span>
                @endif
            </div>

            @if ($teamId)
                <fieldset>
                    <legend class="{{ $label }}">{{ __('Welke FTZ-dienst wil je wijzigen?') }}</legend>
                    <div class="mt-1 space-y-2">
                        @forelse ($this->duties as $duty)
                            <label class="planner-option flex cursor-pointer items-start gap-3 border p-4 transition {{ $dutyKey === $duty['key'] ? 'planner-border-primary planner-tint-primary' : 'border-gray-300 bg-white hover:border-gray-400' }}">
                                <input type="radio" name="duty" value="{{ $duty['key'] }}" wire:model.live="dutyKey" class="mt-1">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-semibold text-gray-900">{{ $duty['label'] }} · {{ $duty['description'] }}</span>
                                    <span class="mt-0.5 block text-sm text-gray-600">
                                        {{ DateFormat::withWeekday($duty['date']) }} {{ __('om :time', ['time' => $duty['time']]) }}
                                        @if ($duty['location']) · {{ $duty['location'] }} @endif
                                    </span>
                                </span>
                            </label>
                        @empty
                            <span class="block text-sm text-gray-500">{{ __('Voor dit team staan er geen komende FTZ-diensten gepland.') }}</span>
                        @endforelse
                    </div>
                    @error('dutyKey')<span class="{{ $error }}">{{ $message }}</span>@enderror
                </fieldset>
            @endif

            @if ($dutyKey)
                @include('planner::livewire.partials.request-reason', ['prefix' => 'duty-request', 'placeholder' => __('Bijvoorbeeld: met welk team of welke dienst zou je kunnen ruilen?')])

                @include('planner::livewire.partials.requester', ['prefix' => 'duty-request'])
            @endif
        </form>
    @endif
</div>
