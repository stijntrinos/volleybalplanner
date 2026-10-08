@php
    use App\Enums\ContactTopic;

    $replyDays = config('site.reply_within_days');
    $sentTopic = ContactTopic::tryFrom((string) session('contact-sent'));
    $email = config('site.email');
    $phone = config('site.phone');
    $privacyUrl = config('site.privacy_url');

    $steps = [
        ['Je vult het formulier in', $replyDays ? "We nemen binnen {$replyDays} werkdagen contact op om een moment te prikken." : 'We nemen snel contact op om een moment te prikken.'],
        ['We bereiden de demo voor', 'Met jullie sporthallen en het Nevobo-programma van dit seizoen.'],
        ['Samen door de planner', 'Dagrooster, diensten en dagschema’s, en jouw vragen.'],
    ];
@endphp

<x-site.layout title="Contact en demo" active="contact" description="Plan een online demo van Volleybalplanner met jullie eigen sporthallen en Nevobo-programma, of stel je vraag.">
    <section class="wrap contact">
        <div class="contact-text">
            <div style="display: flex; flex-direction: column; gap: 14px">
                <span class="kicker">Contact</span>
                <h1 class="title">Plan een demo of stel je vraag</h1>
                <p class="lead">Een demo duurt ongeveer een half uur, online. We gebruiken jullie eigen sporthallen en Nevobo-programma, zodat je meteen ziet hoe jullie seizoen eruit komt te zien.</p>
            </div>
            <div class="contact-steps">
                @foreach ($steps as $index => [$title, $text])
                    <div class="contact-step">
                        <span class="step-number">{{ $index + 1 }}</span>
                        <div><strong>{{ $title }}</strong><span>{{ $text }}</span></div>
                    </div>
                @endforeach
            </div>
            <div class="card contact-direct">
                <strong>Liever direct contact?</strong>
                @if ($email)
                    <div><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#0B7F8A" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18v12H3zM3 7l9 6 9-6"></path></svg><a href="mailto:{{ $email }}">{{ $email }}</a></div>
                @endif
                @if ($phone)
                    <div><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="#0B7F8A" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1"></path></svg><a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a></div>
                @endif
                <p class="muted" style="margin-top: 4px">Al klant en hulp nodig? Gebruik het supportformulier in de beheeromgeving, dan hebben we meteen de juiste planningversie erbij.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('site.contact.store') }}" class="card contact-form" novalidate>
            @csrf

            @if ($sentTopic)
                <div class="notice" role="status">
                    <x-site.check />
                    <div>
                        <strong>Bedankt, je {{ $sentTopic === ContactTopic::Demo ? 'demo-aanvraag' : ($sentTopic === ContactTopic::Quote ? 'offerteaanvraag' : 'vraag') }} is verstuurd.</strong>
                        <span>{{ $replyDays ? "We reageren binnen {$replyDays} werkdagen." : 'We reageren zo snel mogelijk.' }}</span>
                    </div>
                </div>
            @endif

            <fieldset>
                <legend>Waarmee kunnen we helpen?</legend>
                <div class="topics">
                    @foreach (ContactTopic::publicCases() as $case)
                        <label class="topic">
                            <input type="radio" name="topic" value="{{ $case->value }}" data-submit-label="{{ $case->submitLabel() }}" @checked($topic === $case->value)>
                            <span>{{ $case->getLabel() }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="fields">
                @foreach ([
                    ['name', 'Naam', 'text', 'name', true],
                    ['club', 'Vereniging', 'text', 'organization', true],
                    ['email', 'E-mailadres', 'email', 'email', true],
                    ['phone', 'Telefoon', 'tel', 'tel', false],
                ] as [$field, $label, $type, $autocomplete, $required])
                    <div @class(['field', 'has-error' => $errors->has($field)])>
                        <label for="{{ $field }}">{{ $label }} @unless ($required)<small>(optioneel)</small>@endunless</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" value="{{ old($field) }}" @required($required) @if ($errors->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif>
                        @error($field)<span class="field-error" id="{{ $field }}-error">{{ $message }}</span>@enderror
                    </div>
                @endforeach
                <div class="field">
                    <label for="teams">Aantal teams</label>
                    <select id="teams" name="teams">
                        @foreach ($teamOptions as $option)
                            <option @selected(old('teams') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="venues">Aantal sporthallen</label>
                    <select id="venues" name="venues">
                        @foreach ($venueOptions as $option)
                            <option @selected(old('venues') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div @class(['field', 'has-error' => $errors->has('message')])>
                <label for="message">Bericht</label>
                <textarea id="message" name="message" rows="5" placeholder="Bijvoorbeeld: hoe plannen jullie nu, en waar loop je tegenaan?">{{ old('message') }}</textarea>
                @error('message')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="hp" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <div class="consent">
                    <input id="privacy" name="privacy" type="checkbox" value="1" @checked(old('privacy')) required>
                    <label for="privacy">Ik ga akkoord met de @if ($privacyUrl)<a href="{{ $privacyUrl }}">privacyverklaring</a>@else{{ 'privacyverklaring' }}@endif. We gebruiken je gegevens alleen om contact op te nemen.</label>
                </div>
                @error('privacy')<span class="field-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-lg btn-primary" data-submit>{{ (ContactTopic::tryFrom($topic) ?? ContactTopic::Demo)->submitLabel() }}</button>
                @if ($replyDays)
                    <span>We reageren binnen {{ $replyDays }} werkdagen.</span>
                @endif
            </div>
        </form>
    </section>

    <script>
        document.querySelectorAll('input[name="topic"]').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelector('[data-submit]').textContent = input.dataset.submitLabel;
            });
        });
    </script>
</x-site.layout>
