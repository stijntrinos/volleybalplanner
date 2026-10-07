<?php

namespace Tests\Feature\Planner;

use Illuminate\Testing\TestView;
use Tests\TestCase;

class DutyTeamCourtTest extends TestCase
{
    public function test_the_field_shows_the_teams_and_an_empty_choice(): void
    {
        $this->field(['DS 1' => 'DS 1', 'HS 2' => 'HS 2'], 'HS 2')
            ->assertSee('— niet ingevuld —')
            ->assertSee('<option value="DS 1">DS 1</option>', false)
            ->assertSee('<option value="HS 2">HS 2</option>', false)
            ->assertDontSee('Nevobo');
    }

    public function test_nevobo_is_only_listed_when_asked_for(): void
    {
        $this->field(['DS 1' => 'DS 1'], 'Nevobo', nevobo: true)
            ->assertSee('<option value="Nevobo">Nevobo</option>', false);
    }

    public function test_an_unknown_value_stays_selectable(): void
    {
        $this->field(['DS 1' => 'DS 1'], 'MB 7')
            ->assertSee('<option value="MB 7">MB 7 (onbekend)</option>', false);
    }

    public function test_a_rejected_choice_gets_the_reason_below_it(): void
    {
        $this->field(['DS 1' => 'DS 1'], 'DS 1', errors: ['Het team heeft die dag een uitwedstrijd.'])
            ->assertSee('Het team heeft die dag een uitwedstrijd.')
            ->assertSee('border-danger-400', false);
    }

    public function test_the_field_carries_its_path_as_key(): void
    {
        // Livewire does not clean up an old wire:model binding when only the
        // path changes; an own key forces a new element.
        $this->field(['DS 1' => 'DS 1'], null)
            ->assertSee('wire:key="duty-field-draft.matches.0.scorer"', false);
    }

    private function field(array $options, ?string $value, bool $nevobo = false, array $errors = []): TestView
    {
        return $this->blade(
            '<x-planner::duty-team-court :path="$path" :value="$value" :options="$options" :field-errors="$fieldErrors" :label="$label" :nevobo="$nevobo" />',
            [
                'path' => 'draft.matches.0.scorer',
                'value' => $value,
                'options' => $options,
                'fieldErrors' => $errors,
                'label' => 'Teller voor TEST-1',
                'nevobo' => $nevobo,
            ],
        );
    }
}
