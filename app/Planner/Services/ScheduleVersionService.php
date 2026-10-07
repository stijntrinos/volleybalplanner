<?php

namespace App\Planner\Services;

use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Exceptions\PublishBlocked;
use App\Planner\Models\ScheduleVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ScheduleVersionService
{
    public function __construct(private readonly ConflictEngine $conflicts) {}

    /**
     * Publishes a version atomically: the club's currently published version
     * is archived in the same transaction. The unique index on
     * (club_id, is_published) guarantees at most one published version per
     * club, also with concurrent requests.
     */
    public function publish(ScheduleVersion $version, bool $allowConflicts = false): ScheduleVersion
    {
        return DB::transaction(function () use ($version, $allowConflicts) {
            // The scan belongs in the same transaction as the publication:
            // otherwise something can still be moved between "no conflicts"
            // and "published", and you publish a double booking after all.
            $version->matches()->lockForUpdate()->get();
            $conflicts = collect($this->conflicts->scanVersion($version))
                ->where('level', ConflictEngine::LEVEL_CONFLICT);
            if ($conflicts->isNotEmpty() && !$allowConflicts) {
                throw new PublishBlocked($conflicts->values()->all());
            }

            ScheduleVersion::query()
                ->where('status', ScheduleVersionStatus::Published)
                ->whereKeyNot($version->getKey())
                ->update([
                    'status' => ScheduleVersionStatus::Archived,
                    'is_published' => null,
                    'archived_at' => now(),
                ]);

            $version->update([
                'status' => ScheduleVersionStatus::Published,
                'is_published' => true,
                'published_at' => now(),
                'archived_at' => null,
            ]);

            return $version->refresh();
        });
    }

    /** Duplicates a version including all matches with a single INSERT ... SELECT. */
    public function duplicate(ScheduleVersion $source, string $name, ?int $userId = null): ScheduleVersion
    {
        return DB::transaction(function () use ($source, $name, $userId) {
            $copy = ScheduleVersion::query()->create([
                'name' => $name,
                'note' => $source->note,
                'status' => ScheduleVersionStatus::Draft,
                'created_by' => $userId,
            ]);

            $columns = $this->copyableColumns();

            $select = DB::table('planner_matches')
                ->where('version_id', $source->id)
                ->where('club_id', $source->club_id)
                ->select(array_map(
                    fn (string $column) => $column === 'version_id'
                        ? DB::raw((int) $copy->id.' as version_id')
                        : $column,
                    $columns,
                ));

            DB::table('planner_matches')->insertUsing($columns, $select);

            foreach ($source->activities as $activity) {
                $copy->activities()->create($activity->only([
                    'date', 'start_time', 'end_time', 'title', 'venue_id', 'court_id', 'notes',
                ]));
            }
            foreach ($source->hallDuties as $hallDuty) {
                $copy->hallDuties()->create($hallDuty->only([
                    'date', 'start_time', 'end_time', 'team_id', 'venue_id', 'notes',
                ]));
            }
            foreach ($source->volleystarsBlocks()->with('tasks')->get() as $block) {
                $newBlock = $copy->volleystarsBlocks()->create($block->only([
                    'date', 'venue_id', 'setup_start', 'setup_end',
                    'teardown_start', 'teardown_end', 'scoring_start', 'scoring_end', 'notes',
                ]));
                foreach ($block->tasks as $task) {
                    $newBlock->tasks()->create($task->only(['role', 'team_id', 'court_id', 'sort_order']));
                }
            }

            return $copy;
        });
    }

    /**
     * The columns come from the schema instead of a handwritten list. Such a
     * list falls behind with every new column -- the Nevobo change fields
     * were missing, so a copy looked clean while it carried unprocessed
     * federation changes.
     *
     * @return list<string>
     */
    private function copyableColumns(): array
    {
        return array_values(array_diff(
            Schema::getColumnListing('planner_matches'),
            ['id'],
        ));
    }

    public function archive(ScheduleVersion $version): ScheduleVersion
    {
        $version->update([
            'status' => ScheduleVersionStatus::Archived,
            'is_published' => null,
            'archived_at' => now(),
        ]);

        return $version->refresh();
    }

    public function reactivate(ScheduleVersion $version): ScheduleVersion
    {
        $version->update([
            'status' => ScheduleVersionStatus::Draft,
            'archived_at' => null,
        ]);

        return $version->refresh();
    }
}
