<x-site.layout title="Uitnodiging" :noindex="true">
    <section class="wrap invitation">
        <div class="card invitation-card">
            @if ($state === 'invalid')
                <span class="kicker">Uitnodiging</span>
                <h1 class="title">Deze link werkt niet meer</h1>
                <p class="lead">De uitnodiging is al gebruikt, ingetrokken of verlopen. Vraag de beheerder van je vereniging om een nieuwe uitnodiging.</p>
            @else
                <span class="kicker">Uitnodiging · {{ $invitation->club->name }}</span>
                <h1 class="title">Plan mee voor {{ $invitation->club->name }}</h1>
                <p class="lead">
                    Je bent uitgenodigd als <strong>{{ $invitation->role->getLabel() }}</strong> met het e-mailadres
                    <strong>{{ $invitation->email }}</strong>. De uitnodiging werkt tot {{ $invitation->expires_at->translatedFormat('j F Y') }}.
                </p>

                @if ($state === 'accept')
                    <div class="invitation-actions">
                        <form method="POST" action="{{ route('invitations.accept', $token) }}">
                            @csrf
                            <button type="submit" class="btn btn-lg btn-primary">Uitnodiging accepteren</button>
                        </form>
                        <form method="POST" action="{{ route('invitations.decline', $token) }}">
                            @csrf
                            <button type="submit" class="btn btn-lg btn-outline">Afwijzen</button>
                        </form>
                    </div>
                @elseif ($state === 'other-account')
                    <div class="notice" role="status">
                        <div>
                            <strong>Je bent ingelogd met een ander account ({{ auth()->user()->email }}).</strong>
                            <span>Log uit en log in met {{ $invitation->email }} om de uitnodiging te accepteren.</span>
                        </div>
                    </div>
                @elseif ($state === 'login')
                    <p>Er bestaat al een account voor dit e-mailadres. Log in; daarna kom je hier terug om te accepteren.</p>
                    <div class="invitation-actions">
                        <a href="{{ $loginUrl }}" class="btn btn-lg btn-primary">Inloggen</a>
                        <form method="POST" action="{{ route('invitations.decline', $token) }}">
                            @csrf
                            <button type="submit" class="btn btn-lg btn-outline">Afwijzen</button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('invitations.accept', $token) }}" class="invitation-form" novalidate>
                        @csrf
                        <div @class(['field', 'has-error' => $errors->has('name')])>
                            <label for="name">Naam</label>
                            <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name', $invitation->name) }}" required>
                            @error('name')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div @class(['field', 'has-error' => $errors->has('password')])>
                            <label for="password">Kies een wachtwoord</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required>
                            @error('password')<span class="field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="field">
                            <label for="password_confirmation">Herhaal het wachtwoord</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                        </div>
                        <div class="invitation-actions">
                            <button type="submit" class="btn btn-lg btn-primary">Account maken en accepteren</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('invitations.decline', $token) }}">
                        @csrf
                        <button type="submit" class="invitation-decline">Uitnodiging afwijzen</button>
                    </form>
                @endif
            @endif
        </div>
    </section>
</x-site.layout>
