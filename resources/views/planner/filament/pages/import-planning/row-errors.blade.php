@if ($rowErrors ?? [])
    <div class="mt-5 rounded-lg border border-danger-200 bg-danger-50 p-4 dark:border-danger-500/20 dark:bg-danger-500/5">
        @isset($heading)
            <h3 class="mb-2 flex items-center gap-2 text-sm font-semibold text-danger-700 dark:text-danger-400">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />{{ $heading }}
            </h3>
        @endisset
        <ul class="max-h-56 space-y-1 overflow-y-auto text-sm text-danger-700 dark:text-danger-300">
            @foreach ($rowErrors as $error)
                <li><span class="font-medium">{{ __('Rij :row:', ['row' => $error['row']]) }}</span> {{ $error['reason'] }}</li>
            @endforeach
        </ul>
    </div>
@endif
