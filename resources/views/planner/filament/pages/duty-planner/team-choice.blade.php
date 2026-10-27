@php
    use App\Planner\Services\Duties\PointsBalance;
    use App\Planner\Services\Duties\TeamChoice;
@endphp

{{-- All teams in the order of the planning rules, with their standing and
     the reason why a team is or is not proposed. --}}
<x-filament::modal id="duty-team-choice" width="5xl" :heading="$teamChoice['title'] ?? __('Teamkeuze')" :description="$teamChoice['description'] ?? null">
    @if ($teamChoice)
        @if ($teamChoice['balance'])
            <div class="mb-3 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:bg-white/5 dark:text-gray-300">
                {{ __('Nu bij :team: :before van het minimum (nog :before_remaining). Na verplaatsen: :after (nog :after_remaining).', [
                    'team' => $teamChoice['balance']['code'],
                    'before' => PointsBalance::percentageText($teamChoice['balance']['before']),
                    'before_remaining' => $teamChoice['balance']['before_remaining'],
                    'after' => PointsBalance::percentageText($teamChoice['balance']['after']),
                    'after_remaining' => $teamChoice['balance']['after_remaining'],
                ]) }}
            </div>
        @endif
        <div class="overflow-x-auto">
            <table class="w-full min-w-[48rem] text-left text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="px-3 py-2">{{ __('Team') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Totaal geleverd') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Minimum') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Nog in te vullen') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('% van minimum') }}</th>
                        <th class="px-3 py-2 text-right">{{ __('Na keuze') }}</th>
                        <th class="px-3 py-2">{{ __('Redenen') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($teamChoice['candidates'] as $candidate)
                        <tr wire:key="team-choice-{{ $loop->index }}-{{ md5($candidate['code']) }}" @class([
                            'bg-primary-50/60 dark:bg-primary-500/10' => $candidate['code'] === ($teamChoice['current'] ?? null),
                            'opacity-60' => $candidate['status'] === TeamChoice::CANNOT,
                        ])>
                            <td class="px-3 py-2 align-top font-semibold text-gray-950 dark:text-white">
                                {{ $candidate['code'] }}
                                @if ($loop->first && $candidate['status'] === TeamChoice::PROPOSED)
                                    <x-filament::badge color="success" size="sm" class="mt-1">{{ __('Voorstel') }}</x-filament::badge>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right align-top">{{ $candidate['total'] }}</td>
                            <td class="px-3 py-2 text-right align-top">{{ $candidate['has_minimum'] ? $candidate['minimum'] : '-' }}</td>
                            <td class="px-3 py-2 text-right align-top">{{ $candidate['has_minimum'] ? $candidate['remaining'] : '-' }}</td>
                            <td class="px-3 py-2 text-right align-top">{{ PointsBalance::percentageText($candidate['percentage']) }}</td>
                            <td class="px-3 py-2 text-right align-top text-gray-500 dark:text-gray-400">
                                @if ($candidate['has_minimum'])
                                    {{ PointsBalance::percentageText($candidate['after_percentage']) }}
                                    <span class="block text-xs">{{ __('nog :count', ['count' => $candidate['after_remaining']]) }}</span>
                                @else
                                    {{ $candidate['after_total'] }}
                                @endif
                            </td>
                            <td @class([
                                'px-3 py-2 align-top text-xs',
                                'text-danger-600 dark:text-danger-400' => $candidate['status'] === TeamChoice::CANNOT,
                                'text-warning-600 dark:text-warning-400' => $candidate['status'] === TeamChoice::NOT_PROPOSED,
                                'text-gray-500 dark:text-gray-400' => $candidate['status'] === TeamChoice::PROPOSED,
                            ])>
                                @foreach ($candidate['reasons'] as $reason)
                                    <p>{{ $reason }}</p>
                                @endforeach
                            </td>
                            <td class="px-3 py-2 text-right align-top">
                                <x-filament::button size="xs" :color="$candidate['status'] === TeamChoice::PROPOSED ? 'primary' : 'gray'" wire:click="chooseTeam({{ $loop->index }})">
                                    {{ __('Kies') }}
                                </x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-filament::modal>
