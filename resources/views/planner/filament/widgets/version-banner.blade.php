<x-filament-widgets::widget class="vp-widget-bare">
    @if ($published || $draft)
        <div class="vp-banner">
            <x-filament::icon icon="heroicon-o-square-3-stack-3d" class="vp-banner-icon" />
            <p class="vp-banner-text">
                @if ($published)
                    <strong>{{ __(':version is actief', ['version' => $published->name]) }}</strong>
                    @if ($publishedAt) {{ __('sinds :moment.', ['moment' => $publishedAt]) }} @else . @endif
                @else
                    <strong>{{ __('Nog geen planning gepubliceerd.') }}</strong>
                @endif
                @if ($draft)
                    @if ($changes !== null)
                        {{ __('Concept :version heeft :changes en :conflicts.', [
                            'version' => $draft->name,
                            'changes' => trans_choice(':count wijziging|:count wijzigingen', $changes),
                            'conflicts' => trans_choice(':count harde conflict|:count harde conflicten', $conflicts),
                        ]) }}
                    @else
                        {{ __('Concept :version heeft :conflicts.', [
                            'version' => $draft->name,
                            'conflicts' => trans_choice(':count harde conflict|:count harde conflicten', $conflicts),
                        ]) }}
                    @endif
                @endif
            </p>
            @if ($draftUrl)
                <a href="{{ $draftUrl }}" class="vp-banner-link">{{ __('Concept :version openen', ['version' => $draft->name]) }}</a>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
