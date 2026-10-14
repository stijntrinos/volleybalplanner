<x-filament-panels::page>
    <div class="vp-subscription">
        <section class="vp-subscription-plan">
            <div>
                <span class="vp-subscription-kicker">{{ __('Jullie pakket') }}</span>
                <h2>{{ $plan?->name ?? __('Geen pakket') }}</h2>
                @if ($plan?->tagline)
                    <p>{{ $plan->tagline }}</p>
                @endif
                @if ($period)
                    <p class="vp-subscription-period">{{ $period }}</p>
                @endif
            </div>
            <div class="vp-subscription-actions">
                @if ($plan?->formattedPrice())
                    <span class="vp-subscription-price">{{ $plan->formattedPrice() }} <small>{{ __('per seizoen, excl. btw') }}</small></span>
                @endif
                <x-filament::button color="gray" tag="a" :href="route('site.pricing')" target="_blank" icon="heroicon-m-arrow-top-right-on-square" icon-position="after">
                    {{ __('Pakketten vergelijken') }}
                </x-filament::button>
                @if ($renewal)
                    <x-filament::button x-data x-on:click="$dispatch('planner-upgrade', { renew: true })" icon="heroicon-m-calendar-days" :color="$renewal['tone'] === 'danger' ? 'danger' : 'primary'">
                        {{ __('Verlenging aanvragen') }}
                    </x-filament::button>
                @endif
                @if ($canUpgrade)
                    <x-filament::button x-data x-on:click="$dispatch('planner-upgrade', {})" icon="heroicon-m-arrow-up-circle">
                        {{ __('Upgrade aanvragen') }}
                    </x-filament::button>
                @else
                    <span class="vp-subscription-top">
                        <x-filament::icon icon="heroicon-m-check-badge" class="vp-subscription-top-icon" />
                        {{ __('Jullie hebben het grootste pakket') }}
                    </span>
                @endif
            </div>
        </section>

        <section class="vp-subscription-limits">
            @foreach ($limits as ['limit' => $limit, 'used' => $used, 'max' => $max])
                @php
                    $full = $max !== null && $used >= $max;
                @endphp
                <div @class(['vp-subscription-limit', 'is-full' => $full])>
                    <span class="vp-subscription-limit-label">{{ $limit->getLabel() }}</span>
                    <strong>{{ $used }} <small>/ {{ $max ?? __('onbeperkt') }}</small></strong>
                    @if ($max !== null)
                        <span class="vp-subscription-meter" aria-hidden="true"><span style="width: {{ min(100, $max > 0 ? round($used / $max * 100) : 100) }}%"></span></span>
                    @endif
                    @if ($full)
                        <button type="button" class="vp-subscription-limit-upgrade" x-data x-on:click="$dispatch('planner-upgrade', { limit: @js($limit->value) })">{{ __('Limiet bereikt · meer ruimte') }}</button>
                    @endif
                </div>
            @endforeach
        </section>

        <section>
            <h3 class="vp-subscription-heading">{{ __('Modules') }}</h3>
            <p class="vp-subscription-intro">{{ __('Thuiswedstrijden, sporthallen, teams, de Nevobo-sync en exports zitten in elk pakket.') }}</p>
            <div class="vp-subscription-modules">
                @foreach ($modules as ['feature' => $feature, 'included' => $included, 'upgrade' => $upgrade])
                    @if ($included)
                        <div class="vp-subscription-module is-included">
                            <x-filament::icon icon="heroicon-o-check-circle" class="vp-subscription-module-icon" />
                            <div>
                                <strong>{{ $feature->getLabel() }}</strong>
                                <span>{{ $feature->getDescription() }}</span>
                            </div>
                        </div>
                    @else
                        <button type="button" class="vp-subscription-module is-locked" x-data x-on:click="$dispatch('planner-upgrade', { feature: @js($feature->value) })">
                            <x-filament::icon icon="heroicon-o-lock-closed" class="vp-subscription-module-icon" />
                            <div>
                                <strong>{{ $feature->getLabel() }}</strong>
                                <span>{{ $feature->getDescription() }}</span>
                                @if ($upgrade)
                                    <em>{{ __('In :plan', ['plan' => $upgrade]) }}</em>
                                @endif
                            </div>
                        </button>
                    @endif
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
