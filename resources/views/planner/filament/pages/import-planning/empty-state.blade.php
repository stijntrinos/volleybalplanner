<div class="rounded-xl border-2 border-dashed border-gray-200 bg-gray-50/50 px-6 py-12 text-center dark:border-white/10 dark:bg-white/[.02]">
    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-white/5">
        <x-filament::icon :icon="$icon" class="h-7 w-7" />
    </div>
    <h2 class="font-semibold text-gray-950 dark:text-white">{{ $heading }}</h2>
    <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">{{ $text }}</p>
</div>
