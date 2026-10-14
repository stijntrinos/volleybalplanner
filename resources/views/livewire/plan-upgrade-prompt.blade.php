<div
    x-data
    x-on:planner-upgrade.window="$wire.show($event.detail?.feature ?? null, $event.detail?.limit ?? null, $event.detail?.renew ?? false)"
>
    <x-filament::modal
        :id="\App\Livewire\PlanUpgradePrompt::MODAL"
        width="lg"
        :icon="$renew ? 'heroicon-o-calendar-days' : ($module || $cap ? 'heroicon-o-lock-closed' : 'heroicon-o-arrow-up-circle')"
        icon-color="primary"
        :heading="$renew ? __('Abonnement verlengen') : ($module ? __(':module zit niet in je pakket', ['module' => $module->getLabel()]) : ($cap ? __('Je zit aan de limiet van je pakket') : __('Naar een groter pakket')))"
    >
        <div class="vp-upgrade">
            @if ($renew)
                <p>
                    @if ($renewal){{ $renewal['message'] }} @endif
                    {{ __('Vraag de verlenging aan, dan nemen we contact met je op. Zodra het abonnement verlengd is, kun je meteen weer alles wijzigen.') }}
                </p>
            @elseif ($module)
                <p>{{ $module->getDescription() }}</p>
            @elseif ($limitMessage)
                <p>{{ $limitMessage }} {{ __('Bestaande gegevens blijven gewoon staan; alleen toevoegen kan niet meer.') }}</p>
            @endif

            @if ($target)
                <div class="vp-upgrade-plan">
                    <div class="vp-upgrade-plan-head">
                        <div>
                            <span class="vp-upgrade-kicker">{{ $renew ? __('Jullie pakket') : ($club?->planName() ? __('Van :from naar', ['from' => $club->planName()]) : __('Beschikbaar in')) }}</span>
                            <strong>{{ $target->name }}</strong>
                        </div>
                        <span class="vp-upgrade-price">
                            {{ $target->formattedPrice() ?? __('Op aanvraag') }}
                            @if ($target->formattedPrice())<small>{{ __('per seizoen') }}</small>@endif
                        </span>
                    </div>
                    @if ($target->tagline)
                        <p>{{ $target->tagline }}</p>
                    @endif
                    @if ($target->highlights)
                        <ul>
                            @foreach ($target->highlights as $highlight)
                                <li>
                                    <x-filament::icon icon="heroicon-m-check" class="vp-upgrade-check" />
                                    <span>{{ $highlight }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @else
                <p>{{ __('Neem contact met ons op, dan kijken we samen wat past.') }}</p>
            @endif

            @if ($requested)
                <div class="vp-upgrade-sent" role="status">
                    <x-filament::icon icon="heroicon-o-check-circle" class="vp-upgrade-sent-icon" />
                    <span>{{ $replyDays ? __('Aanvraag verstuurd. We nemen binnen :days werkdagen contact op.', ['days' => $replyDays]) : __('Aanvraag verstuurd. We nemen snel contact op.') }}</span>
                </div>
            @endif
        </div>

        <x-slot name="footerActions">
            @unless ($requested)
                <x-filament::button wire:click="requestUpgrade" wire:loading.attr="disabled" :icon="$renew ? 'heroicon-m-paper-airplane' : 'heroicon-m-arrow-up-circle'">
                    {{ $renew ? __('Verlenging aanvragen') : ($target ? __('Upgrade naar :plan aanvragen', ['plan' => $target->name]) : __('Contact opnemen')) }}
                </x-filament::button>
            @endunless
            <x-filament::button tag="a" :href="route('site.pricing')" target="_blank" color="gray">
                {{ __('Pakketten vergelijken') }}
            </x-filament::button>
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: '{{ \App\Livewire\PlanUpgradePrompt::MODAL }}' })">
                {{ __('Sluiten') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <script>
        // Locked links (#upgrade:<module>) open this prompt instead of navigating.
        if (!window.__planUpgradeBound) {
            window.__planUpgradeBound = true;
            document.addEventListener('click', (event) => {
                const link = event.target.closest?.('a[href*="#upgrade:"]');
                if (!link) {
                    return;
                }
                event.preventDefault();
                event.stopImmediatePropagation();
                window.dispatchEvent(new CustomEvent('planner-upgrade', {
                    detail: { feature: link.getAttribute('href').split('#upgrade:')[1] || null },
                }));
            }, true);
        }
    </script>
</div>
