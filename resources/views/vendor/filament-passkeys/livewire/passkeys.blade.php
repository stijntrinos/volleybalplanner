{{-- Override of marcelweidum/filament-passkeys: a cancelled or refused passkey shows a notification instead of an uncaught error. --}}
<div>
    <div>
        <form
            id="passkeyForm"
            x-data="{
                name: @entangle('name'),
                async register() {
                    if (! this.name?.trim()) {
                        return
                    }

                    try {
                        await window.FilamentPasskeys.register(this.name)
                        await $wire.passkeyCreated()
                    } catch (error) {
                        if (error?.name === 'PasskeyExistsError') {
                            await $wire.passkeyAlreadyExists()

                            return
                        }

                        {{-- Cancelled by the user, refused by the browser (often: this device already has a
                             passkey for this account) or timed out: tell the user instead of failing silently. --}}
                        if (['UserCancelledError', 'NotAllowedError', 'AbortError', 'InvalidStateError'].includes(error?.name)) {
                            new FilamentNotification()
                                .title(@js(__('Passkey niet toegevoegd')))
                                .body(@js(__('Je hebt het venster gesloten of je browser weigerde. Heeft dit apparaat al een passkey voor je account, dan kun je die gewoon gebruiken om in te loggen.')))
                                .warning()
                                .send()

                            return
                        }

                        throw error
                    }
                },
            }"
            x-on:submit.prevent="register"
            class="flex items-start space-x-2"
        >
            <div class="w-full fi-fo-field">
                <x-filament::input.wrapper prefix="{{ __('filament-passkeys::passkeys.name') }}" :valid="! $errors->has('name')">
                    <x-filament::input
                        type="text"
                        x-model="name"
                        autocomplete="off"
                        placeholder="{{ __('filament-passkeys::passkeys.name_placeholder') }}"
                    />
                </x-filament::input.wrapper>

                @error('name')
                    <p class="fi-fo-field-wrp-error-message">{{ $message }}</p>
                @enderror
            </div>

            <x-filament::button type="submit">
                {{ __('filament-passkeys::passkeys.create') }}
            </x-filament::button>
        </form>
    </div>

    @if($passkeys->isNotEmpty())
        <div class="mt-6">
            <span class="font-bold text-sm">{{ __('filament-passkeys::passkeys.passkeys') }}</span>
            <ul class="space-y-4">
                @foreach($passkeys as $passkey)
                    <x-filament::fieldset class="mt-2">
                        <div class="flex items-center">
                            <div class="me-2 flex flex-col">
                                <span>{{ $passkey->name }}</span>
                                <span class="text-xs fi-sc-text">{{ __('filament-passkeys::passkeys.last_used') }}: {{ $passkey->last_used_at?->diffForHumans() ?? __('filament-passkeys::passkeys.not_used_yet') }}</span>
                            </div>

                            <div class="ms-auto">
                                {{ ($this->deleteAction)(['passkey' => $passkey->id]) }}
                            </div>
                        </div>
                    </x-filament::fieldset>
                @endforeach
            </ul>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
