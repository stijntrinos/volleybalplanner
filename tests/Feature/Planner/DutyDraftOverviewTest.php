<?php

namespace Tests\Feature\Planner;

use App\Planner\Services\Duties\DraftOverview;
use Tests\TestCase;

class DutyDraftOverviewTest extends TestCase
{
    public function test_hall_duties_are_chronologically_between_the_matches(): void
    {
        $blocks = $this->overview($this->draft());

        $this->assertCount(1, $blocks);
        $this->assertSame([
            ['hall_duty', '08:15–09:00'],
            ['match', '09:00'],
            ['match', '09:00'],
            ['hall_duty', '10:45–12:30'],
            ['match', '10:45'],
        ], array_map(fn (array $row): array => [$row['type'], $row['time']], $blocks[0]['rows']));
    }

    public function test_each_block_is_one_playing_day_in_one_venue(): void
    {
        $draft = $this->draft();
        $draft['matches'][] = [
            'id' => 99, 'date' => '2026-09-12', 'time' => '14:00', 'venue_id' => 2,
            'venue' => 'Rijkerswoerd', 'code' => 'X-1', 'home' => 'AETOS HS 2', 'away' => 'Gast',
            'referee' => null, 'scorer' => null,
        ];

        $blocks = $this->overview($draft);

        $this->assertCount(2, $blocks);
        $this->assertSame(['Middachtensingel', 'Rijkerswoerd'], array_column($blocks, 'venue'));
        $this->assertSame(2, $blocks[1]['venue_id']);
    }

    public function test_the_main_venue_comes_first(): void
    {
        $draft = $this->draft();
        $draft['matches'][] = [
            'id' => 99, 'date' => '2026-09-12', 'time' => '14:00', 'venue_id' => 2,
            'venue' => 'Rijkerswoerd', 'code' => 'X-1', 'referee' => null, 'scorer' => null,
        ];

        $blocks = app(DraftOverview::class)->blocks($draft, [], 2);

        $this->assertSame(['Rijkerswoerd', 'Middachtensingel'], array_column($blocks, 'venue'));
    }

    public function test_the_indexes_point_to_the_draft(): void
    {
        $rows = collect($this->overview($this->draft())[0]['rows']);

        $match = $rows->firstWhere('code', 'W-2');
        $this->assertSame(1, $match['index']);
        $this->assertSame('DS 8', $match['referee']['value']);
        $this->assertSame(1, $rows->where('type', 'hall_duty')->last()['index']);
    }

    public function test_the_counts_per_block_only_count_that_block(): void
    {
        $blocks = $this->overview($this->draft(), [
            'matches' => [2 => ['scorer' => ['Het team heeft die dag een uitwedstrijd.']]],
            'hall_duties' => [0 => ['Het team heeft die dag een uitwedstrijd.']],
            'count' => 2,
        ]);

        $this->assertSame([
            'matches' => 3,
            'referee' => 2,
            'scorer' => 3,
            'hall_duties' => 1,
            'issues' => 2,
        ], $blocks[0]['counts']);
    }

    public function test_rejections_hang_on_the_right_field(): void
    {
        $rows = collect($this->overview($this->draft(), [
            'matches' => [2 => ['scorer' => ['Niet voorgesteld: Het team traint op dat moment.']]],
            'hall_duties' => [0 => ['Het team heeft die dag een uitwedstrijd.']],
        ])[0]['rows']);

        // The C youth time limit is a club preference the committee may
        // deliberately overrule, so it is listed with the warnings.
        $this->assertSame(['Niet voorgesteld: Het team traint op dat moment.'], $rows->firstWhere('code', 'W-2')['scorer']['warnings']);
        $this->assertSame([], $rows->firstWhere('code', 'W-2')['scorer']['errors']);
        $this->assertSame([], $rows->firstWhere('code', 'W-2')['referee']['errors']);
        $this->assertSame(['Het team heeft die dag een uitwedstrijd.'], $rows->firstWhere('type', 'hall_duty')['team']['errors']);
    }

    public function test_a_warning_does_not_count_as_an_issue(): void
    {
        $blocks = $this->overview($this->draft(), [
            'matches' => [2 => ['scorer' => ['Niet voorgesteld: Het team traint op dat moment.']]],
        ]);

        $this->assertSame(0, $blocks[0]['counts']['issues'], 'A deliberate choice should not block applying.');
    }

    public function test_a_real_error_still_counts(): void
    {
        $blocks = $this->overview($this->draft(), [
            'matches' => [2 => ['scorer' => ['Het team heeft die dag een uitwedstrijd.']]],
        ]);

        $this->assertSame(1, $blocks[0]['counts']['issues']);
    }

    public function test_the_origin_of_a_value_is_visible(): void
    {
        $draft = $this->draft();
        $draft['matches'][0]['scorer'] = 'HS 1';
        $draft['matches'][0]['suggested_scorer'] = 'DS 3';
        $draft['matches'][2]['locked'] = ['scorer' => true];

        $rows = collect($this->overview($draft)[0]['rows']);

        $this->assertSame('De bond wijst een scheidsrechter aan.', $rows->firstWhere('code', 'W-1')['referee']['origin']);
        $this->assertSame('Handmatig gekozen.', $rows->firstWhere('code', 'W-1')['scorer']['origin']);
        $this->assertSame('Automatisch voorgesteld door de planner.', $rows->firstWhere('code', 'W-2')['scorer']['origin']);
        $this->assertSame('Vastgezet: blijft staan bij opnieuw genereren.', $rows->firstWhere('code', 'W-3')['scorer']['origin']);
        $this->assertSame('Nog niet ingevuld.', $rows->firstWhere('code', 'W-3')['referee']['origin']);
    }

    private function overview(array $draft, array $validation = []): array
    {
        return app(DraftOverview::class)->blocks($draft, $validation);
    }

    private function draft(): array
    {
        return [
            'matches' => [
                [
                    'id' => 1, 'date' => '2026-09-12', 'time' => '09:00', 'venue_id' => 1,
                    'venue' => 'Middachtensingel', 'court' => '1', 'code' => 'W-1',
                    'home' => 'AETOS JC 1', 'away' => 'AETOS MC 2',
                    'referee' => 'Nevobo', 'scorer' => 'JC 1',
                    'suggested_referee' => 'Nevobo', 'suggested_scorer' => 'JC 1',
                ],
                [
                    'id' => 2, 'date' => '2026-09-12', 'time' => '09:00', 'venue_id' => 1,
                    'venue' => 'Middachtensingel', 'court' => '2', 'code' => 'W-2',
                    'home' => 'AETOS JC 2', 'away' => 'D.V.C. Rebelle MC 1',
                    'referee' => 'DS 8', 'scorer' => 'JC 2',
                    'suggested_referee' => 'DS 8', 'suggested_scorer' => 'JC 2',
                ],
                [
                    'id' => 3, 'date' => '2026-09-12', 'time' => '10:45', 'venue_id' => 1,
                    'venue' => 'Middachtensingel', 'court' => '1', 'code' => 'W-3',
                    'home' => 'AETOS JB 1', 'away' => 'SV Dynamo Apeldoorn JB 1',
                    'referee' => null, 'scorer' => 'JB 1',
                    'suggested_referee' => null, 'suggested_scorer' => 'JB 1',
                ],
            ],
            'hall_duties' => [
                ['date' => '2026-09-12', 'venue_id' => 1, 'venue' => 'Middachtensingel', 'start' => '08:15', 'end' => '09:00', 'team_code' => 'JA 1'],
                ['date' => '2026-09-12', 'venue_id' => 1, 'venue' => 'Middachtensingel', 'start' => '10:45', 'end' => '12:30', 'team_code' => null],
            ],
        ];
    }
}
