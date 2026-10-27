<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The duty rules move from the club (planner_settings.duty_rules) to the team:
 * who never referees, who may referee the restricted teams, who does not
 * score late or at the heavy teams, who gets no hall duty. The conversion
 * keeps today's behaviour. It deliberately uses no app code, so a later
 * change to Team or TeamCode does not change this migration.
 */
return new class extends Migration
{
    private const LOWER_CLASS_TERMS = ['3e', '4e', 'derde', 'vierde'];

    private const HALL_DUTY_CODES = ['HS', 'DS', 'H', 'D', 'JA', 'MA'];

    public function up(): void
    {
        Schema::table('planner_teams', function (Blueprint $table): void {
            $table->date('scores_own_match_until')->nullable()->after('scores_own_match');
            $table->boolean('no_scoring_heavy_teams')->default(false);
            $table->boolean('no_scoring_late')->default(false);
            $table->boolean('no_hall_duty')->default(false);
            $table->boolean('never_referees')->default(false);
            $table->boolean('referees_restricted_teams')->default(false);
            $table->boolean('referees_c_youth')->default(false);
            $table->boolean('referees_youth_and_lower_women')->default(false);
            $table->unsignedSmallInteger('weekly_max')->nullable();
            $table->string('playing_group', 20)->nullable();
        });

        $this->convertClubRules();
    }

    public function down(): void
    {
        Schema::table('planner_teams', function (Blueprint $table): void {
            $table->dropColumn([
                'scores_own_match_until', 'no_scoring_heavy_teams', 'no_scoring_late', 'no_hall_duty',
                'never_referees', 'referees_restricted_teams', 'referees_c_youth', 'referees_youth_and_lower_women',
                'weekly_max', 'playing_group',
            ]);
        });
    }

    private function convertClubRules(): void
    {
        $rulesPerClub = DB::table('planner_settings')->pluck('duty_rules', 'club_id')
            ->map(fn (?string $json): array => json_decode((string) $json, true) ?: []);

        foreach (DB::table('planner_teams')->get(['id', 'club_id', 'code', 'name']) as $team) {
            $rules = $rulesPerClub[$team->club_id] ?? [];
            $code = $this->key($team->code);
            $category = preg_match('/^(HS|DS|JA|MA|JB|MB|JC|MC|H|D)\d+$/', $code, $match) === 1 ? $match[1] : '';
            $requirements = (array) ($rules['referee_requirements'] ?? []);
            $allowed = array_map($this->key(...), collect($requirements)->flatten()->all());

            DB::table('planner_teams')->where('id', $team->id)->update([
                // B and C youth never refereed, nor did the teams on the "no referee" list.
                'never_referees' => in_array($category, ['JB', 'MB', 'JC', 'MC'], true)
                    || in_array($code, array_map($this->key(...), (array) ($rules['no_referee'] ?? [])), true),
                'referees_restricted_teams' => in_array($code, $allowed, true),
                // A youth refereed youth matches; that is now "referees C youth".
                'referees_c_youth' => in_array($category, ['JA', 'MA'], true),
                'referees_youth_and_lower_women' => in_array($category, ['DS', 'D'], true)
                    && $this->isLowerClass((string) $team->name, (array) ($rules['lower_class_terms'] ?? self::LOWER_CLASS_TERMS)),
                // C youth did not score for the heavy teams, nor in the evening.
                'no_scoring_heavy_teams' => in_array($category, ['JC', 'MC'], true) && ($rules['scorer_heavy_teams'] ?? []) !== [],
                'no_scoring_late' => in_array($category, ['JC', 'MC'], true),
                'no_hall_duty' => !$this->mayDoHallDuty($code, (array) ($rules['hall_duty_codes'] ?? self::HALL_DUTY_CODES)),
            ]);
        }

        // What stays per club: the home teams that only the flagged teams may
        // referee. The allowed teams are now a flag on the team itself.
        foreach ($rulesPerClub as $clubId => $rules) {
            if ($rules === []) {
                continue;
            }

            $rules['restricted_referee_teams'] = array_values(array_map('strval', array_keys((array) ($rules['referee_requirements'] ?? []))));
            unset($rules['referee_requirements'], $rules['no_referee'], $rules['hall_duty_codes']);

            DB::table('planner_settings')->where('club_id', $clubId)->update(['duty_rules' => json_encode($rules)]);
        }
    }

    private function key(mixed $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) $value) ?? '');
    }

    /** @param list<string> $prefixes */
    private function mayDoHallDuty(string $code, array $prefixes): bool
    {
        foreach (array_filter(array_map($this->key(...), $prefixes)) as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $terms */
    private function isLowerClass(string $text, array $terms): bool
    {
        $terms = array_filter(array_map('strval', $terms));
        if ($terms === [] || $text === '') {
            return false;
        }

        $pattern = implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms));

        return preg_match('/(?<![A-Z0-9])('.$pattern.')(?![A-Z0-9])/i', $text) === 1;
    }
};
