@php
    use App\Planner\Features\PlannerFeature;

    /** @var \Illuminate\Support\Collection<int, \App\Models\Plan> $plans */
    $trialWeeks = config('site.trial_weeks');
    $hosting = config('site.hosting');
    $amount = fn (?int $value): string => $value === null ? 'onbeperkt' : (string) $value;

    $rows = [
        ['Aantal teams', $plans->map(fn ($plan) => $plan->max_teams === null ? 'onbeperkt' : 'tot '.$plan->max_teams)->all()],
        ['Sporthallen', $plans->map(fn ($plan) => $amount($plan->max_venues))->all()],
        ['Beheerders', $plans->map(fn ($plan) => $amount($plan->max_admins))->all()],
        ['Dagrooster en verplaatsingsassistent', $plans->map(fn () => 'Ja')->all()],
        ['Nevobo-sync elke nacht', $plans->map(fn () => 'Ja')->all()],
        ['Planningversies (concept en actief)', $plans->map(fn () => 'Ja')->all()],
        ...array_map(fn (PlannerFeature $feature): array => [
            $feature->getLabel(),
            $plans->map(fn ($plan) => $plan->includes($feature) ? 'Ja' : '—')->all(),
        ], PlannerFeature::cases()),
    ];

    $faq = [
        ['Kunnen we het eerst uitproberen?', $trialWeeks
            ? "Ja. Na de demo kun je {$trialWeeks} weken proberen met jullie eigen Nevobo-programma, zonder verplichtingen."
            : 'Ja. Na de demo kun je een proefperiode starten met jullie eigen Nevobo-programma, zonder verplichtingen.'],
        ['Hoe werkt de koppeling met de Nevobo?', 'Je leest het programma één keer in als concept. Daarna vergelijkt de planner elke nacht met de Nevobo-feeds en zet afwijkingen op het dashboard.'],
        ['Kunnen we later overstappen naar een groter pakket?', 'Ja, op elk moment. Modules die niet in je pakket zitten zie je in de planner met een slotje; daar vraag je de upgrade ook direct aan.'],
        ['We beginnen halverwege het seizoen. Kan dat?', 'Ja. Je importeert het lopende programma en betaalt naar rato voor de rest van het seizoen.'],
        ['Hoeveel mensen kunnen inloggen?', 'Dat hangt af van het pakket. Leden en teams hebben geen account nodig: zij zien het publieke programma en gebruiken de verzoeklinks.'],
        ['Waar staan onze gegevens?', $hosting
            ? "De gegevens staan bij {$hosting}. We sluiten een verwerkersovereenkomst met de vereniging."
            : 'We sluiten een verwerkersovereenkomst met de vereniging; daarin staat precies waar en hoe de gegevens bewaard worden.'],
    ];
@endphp

<x-site.layout title="Prijzen" active="pricing" description="Eén prijs per seizoen, op basis van het aantal teams. Geen kosten per gebruiker: de hele zaalcommissie werkt mee.">
    <section class="page-intro centered">
        <div class="wrap">
            <span class="kicker">Prijzen</span>
            <h1 class="title">Eén prijs per seizoen, passend bij je vereniging</h1>
            <p class="lead" style="max-width: 620px">Geen kosten per gebruiker: de hele zaalcommissie werkt mee. Je betaalt per seizoen, op basis van het aantal teams.</p>
        </div>
    </section>

    <section class="wrap" style="padding-bottom: 72px">
        <div class="grid grid-300 plans">
            @foreach ($plans as $index => $plan)
                @php
                    $unlimited = $plan->max_teams === null;
                @endphp
                <div @class(['card', 'plan', 'is-featured' => $plan->is_featured])>
                    <div class="plan-head">
                        <h2>{{ $plan->name }}</h2>
                        @if ($plan->is_featured)
                            <span class="badge ok" style="padding: 4px 10px">Meest gekozen</span>
                        @endif
                    </div>
                    @if ($plan->tagline)
                        <p class="plan-for">{{ $plan->tagline }}</p>
                    @endif
                    <div class="plan-price">
                        <strong>{{ $plan->formattedPrice() ?? ($unlimited ? 'Op maat' : 'Op aanvraag') }}</strong>
                        @if ($plan->formattedPrice())
                            <span>per seizoen, excl. btw</span>
                        @endif
                    </div>
                    <a href="{{ $unlimited ? route('site.contact', ['onderwerp' => 'offerte']) : route('site.contact') }}" @class(['btn', 'btn-lg', 'btn-primary' => $plan->is_featured, 'btn-outline' => ! $plan->is_featured])>{{ $unlimited ? 'Neem contact op' : 'Demo aanvragen' }}</a>
                    @if ($plan->highlights)
                        <div class="plan-items">
                            <span>{{ $index === 0 ? 'Wat je krijgt' : 'Alles uit '.$plans[$index - 1]->name.', plus' }}</span>
                            <ul class="check-list">
                                @foreach ($plan->highlights as $item)
                                    <li><x-site.check /><span>{{ $item }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
        <p class="muted" style="margin-top: 16px; font-size: 14px; text-align: center">Instappen halverwege het seizoen? Dan betaal je naar rato. Alle pakketten bevatten de nachtelijke Nevobo-sync en support per e-mail.</p>
    </section>

    <section class="wrap section no-top" style="gap: 20px; padding-bottom: 72px">
        <h2 class="section-title" style="font-size: 30px">Vergelijk de pakketten</h2>
        <div class="card compare">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Onderdeel</th>
                        @foreach ($plans as $plan)
                            <th scope="col" @class(['is-featured' => $plan->is_featured])>{{ $plan->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as [$name, $values])
                        <tr>
                            <th scope="row">{{ $name }}</th>
                            @foreach ($values as $column => $value)
                                <td @class(['is-featured' => $plans[$column]->is_featured])>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="wrap section no-top faq">
        <h2 class="section-title" style="font-size: 30px; margin-bottom: 4px">Veelgestelde vragen</h2>
        @foreach ($faq as [$question, $answer])
            <details>
                <summary>{{ $question }}</summary>
                <p>{{ $answer }}</p>
            </details>
        @endforeach
    </section>

    <x-site.cta
        title="Twijfel je welk pakket past?"
        text="Vertel ons hoeveel teams en sporthallen jullie hebben, dan rekenen we het voor je uit."
        :primary="['Neem contact op', route('site.contact', ['onderwerp' => 'vraag'])]"
    />
</x-site.layout>
