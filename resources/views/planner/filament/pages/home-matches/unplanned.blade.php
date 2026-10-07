<aside class="planner-unplanned">
    <div class="planner-panel-title"><div><h3>{{ __('Nog te plannen wedstrijden') }}</h3><p>{{ __('Plan een wedstrijd of handel een voorstel af') }}</p></div><span>{{ count($day['to_plan'] ?? []) }}</span></div>
    <div class="planner-unplanned-list">
        @forelse ($day['to_plan'] ?? [] as $match)
            <article class="planner-match planner-unplanned-card {{ $editable ? '' : 'is-readonly' }}" data-match-id="{{ $match['id'] }}">
                @php
                    $toMove = $match['to_change'] ?? false;
                    $awaitingApproval = ! $toMove && ($match['change_status'] || $match['change_requested']);
                @endphp
                <div><strong>{{ $match['match_code'] }}</strong>@if ($awaitingApproval)<em>{{ __('Wacht op akkoord') }}</em>@elseif ($toMove)<em>{{ __('Moet verplaatst worden') }}</em>@endif</div>
                <span>{{ $match['home_team'] }}</span>
                <small>{{ __('tegen :team', ['team' => $match['away_team']]) }}</small>
                @if ($toMove)
                    <dl class="planner-change-route">
                        <div><dt>{{ __('Staat nu op') }}</dt><dd>{{ $match['proposal'] ?? __('Nog niet gepland') }}</dd></div>
                        <div><dt>{{ __('Nieuwe plek') }}</dt><dd>{{ __('Nog niet bekend') }}</dd></div>
                    </dl>
                    @if ($match['change_requested_at'])
                        <small class="planner-change-date">{{ __('Gemarkeerd :moment', ['moment' => $match['change_requested_at']]) }}</small>
                    @endif
                    @if ($editable)
                        <div class="planner-change-actions">
                            <button type="button" wire:click.stop="clearMarker({{ $match['id'] }})">{{ __('Markering weghalen') }}</button>
                            <button type="button" wire:click.stop="openAssistant({{ $match['id'] }})">{{ __('Assistent') }}</button>
                        </div>
                    @endif
                @endif
                @if ($awaitingApproval)
                    <dl class="planner-change-route">
                        <div><dt>{{ ($match['has_proposal'] ?? false) ? __('Staat nu op') : __('Oorspronkelijk') }}</dt><dd>{{ $match['original'] ?? __('Nog niet gepland') }}</dd></div>
                        <div><dt>{{ __('Voorstel') }}</dt><dd>{{ $match['proposal'] ?? __('Nog geen voorstel gekozen') }}</dd></div>
                    </dl>
                    @if ($match['change_requested_at'])
                        <small class="planner-change-date">{{ __('Aangevraagd :moment', ['moment' => $match['change_requested_at']]) }}</small>
                    @endif
                    @if ($editable)
                        <div class="planner-change-actions">
                            <button type="button" wire:click.stop="cancelChangeRequest({{ $match['id'] }})" wire:confirm="{{ ($match['has_proposal'] ?? false) ? __('Het voorstel afwijzen? De wedstrijd blijft staan waar hij nu staat.') : __('Het voorstel afwijzen en de wedstrijd terugzetten op de oorspronkelijke plek?') }}">{{ __('Afwijzen') }}</button>
                            <button type="button" wire:click.stop="markNevoboApplied({{ $match['id'] }})" wire:confirm="{{ __('Is het voorstel door de tegenstander bevestigd en in Nevobo verwerkt?') }}">{{ __('Bevestigd') }}</button>
                        </div>
                    @endif
                @endif
            </article>
        @empty
            <p class="planner-muted">{{ __('Alles is ingepland.') }}</p>
        @endforelse
    </div>
</aside>
