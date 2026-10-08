<ul class="vp-invitations">
    @forelse ($invitations as $invitation)
        <li class="vp-invitation">
            <div>
                <strong>{{ $invitation->name }}</strong>
                <span>{{ $invitation->email }} · {{ $invitation->role->getLabel() }}</span>
                <small>Geldig tot {{ $invitation->expires_at->translatedFormat('j F') }}@if ($invitation->inviter) · uitgenodigd door {{ $invitation->inviter->name }}@endif</small>
            </div>
            <div class="vp-invitation-actions">
                <x-filament::button size="sm" color="gray" wire:click="resendInvitation({{ $invitation->id }})" wire:loading.attr="disabled">
                    Opnieuw versturen
                </x-filament::button>
                <x-filament::button size="sm" color="danger" outlined wire:click="revokeInvitation({{ $invitation->id }})" wire:loading.attr="disabled">
                    Intrekken
                </x-filament::button>
            </div>
        </li>
    @empty
        <li class="vp-invitation-empty">Geen openstaande uitnodigingen.</li>
    @endforelse
</ul>
