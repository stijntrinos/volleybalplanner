<x-filament-panels::page>
    @php
        $sections = $this->sections();
    @endphp
    <div class="rounded-xl bg-white p-2 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div @class(['grid gap-2', 'md:grid-cols-3' => count($sections) <= 3, 'md:grid-cols-3 xl:grid-cols-5' => count($sections) > 3])>
            @foreach ($sections as $key => [$label, $description, $icon])
                <button
                    type="button"
                    wire:click="$set('section', '{{ $key }}')"
                    @class([
                        'flex items-center gap-3 rounded-lg px-4 py-3 text-left transition',
                        'bg-primary-50 text-primary-700 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/20' => $section === $key,
                        'text-gray-600 hover:bg-gray-50 hover:text-gray-950 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' => $section !== $key,
                    ])
                >
                    <x-filament::icon :icon="$icon" class="h-6 w-6 shrink-0" />
                    <span>
                        <span class="block text-sm font-semibold">{{ $label }}</span>
                        <span class="mt-0.5 block text-xs opacity-75">{{ $description }}</span>
                    </span>
                </button>
            @endforeach
        </div>
    </div>

    @switch(array_key_exists($section, $sections) ? $section : 'matches')
        @case('trainings')
            @include('planner::filament.pages.import-planning.trainings')
            @break
        @case('availability')
            @include('planner::filament.pages.import-planning.availability')
            @break
        @case('duties')
            @include('planner::filament.pages.import-planning.duties')
            @break
        @case('cleanup')
            @include('planner::filament.pages.import-planning.cleanup')
            @break
        @default
            @include('planner::filament.pages.import-planning.matches')
    @endswitch

    <x-filament-actions::modals />
</x-filament-panels::page>
