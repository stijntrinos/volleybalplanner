<?php

use App\Models\Club;
use App\Planner\Support\CommitteeRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The planning committee gets a role per club, so a club admin can decide
 * what the committee may do (null = everything, as before). Existing members
 * move from the shared role to their club's own role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->json('committee_groups')->nullable()->after('plan_id');
        });

        $roles = config('permission.table_names.roles');
        $assignments = config('permission.table_names.model_has_roles');
        $team = config('permission.column_names.team_foreign_key');
        $shared = DB::table($roles)->where('name', 'planning_committee')->whereNull($team)->value('id');

        if ($shared === null) {
            return;
        }

        // Out of the way first, or creating the club roles of the same name
        // would collide with it.
        DB::table($roles)->where('id', $shared)->update(['name' => 'planning_committee_shared_old']);

        foreach (Club::query()->get() as $club) {
            $own = CommitteeRole::sync($club);
            DB::table($assignments)->where('role_id', $shared)->where($team, $club->id)->update(['role_id' => $own->id]);
        }

        DB::table(config('permission.table_names.role_has_permissions'))->where('role_id', $shared)->delete();
        DB::table($assignments)->where('role_id', $shared)->delete();
        DB::table($roles)->where('id', $shared)->delete();
    }

    public function down(): void
    {
        Schema::table('clubs', function (Blueprint $table): void {
            $table->dropColumn('committee_groups');
        });
    }
};
