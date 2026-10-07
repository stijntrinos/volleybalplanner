@if ($confirmation)
    @php $fromModal = ($confirmation['source'] ?? 'drag') === 'modal'; @endphp
    <div
        class="planner-assistant-backdrop planner-confirm-backdrop"
        wire:click.self="cancelConfirmation"
        wire:keydown.escape.window="cancelConfirmation"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="planner-confirm-title"
    >
        <div class="planner-match-modal planner-confirm">
            <h2 id="planner-confirm-title">{{ __('Deze plaatsing geeft conflicten') }}</h2>
            <p class="planner-confirm-subject">{{ __(':match om :time', ['match' => $confirmation['description'] ?: __('Deze wedstrijd'), 'time' => $confirmation['time']]) }}</p>
            <ul class="planner-confirm-list">
                @foreach ($confirmation['conflicts'] as $conflict)
                    <li class="is-{{ $conflict['level'] ?? 'warning' }}">
                        <span>{{ ($conflict['level'] ?? '') === 'conflict' ? __('Conflict') : __('Let op') }}</span>
                        {{ $conflict['message'] }}
                    </li>
                @endforeach
            </ul>
            <div class="planner-match-modal-actions">
                <button type="button" wire:click="cancelConfirmation">{{ $fromModal ? __('Aanpassen') : __('Terugzetten') }}</button>
                <span></span>
                <button type="button" class="is-primary" wire:click="confirmMove" autofocus>{{ $fromModal ? __('Toch opslaan') : __('Toch verplaatsen') }}</button>
            </div>
        </div>
    </div>
@endif
