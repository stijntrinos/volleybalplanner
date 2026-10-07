<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicClubRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'club'])->prefix('{club}')->group(function (): void {
            Route::get('probe', fn (Club $club) => $club->slug.':'.app(CurrentClub::class)->id());
            Route::get('duties-probe', fn () => 'ok')->middleware('feature:duties');
        });
    }

    public function test_known_active_club_is_resolved(): void
    {
        $club = Club::factory()->create(['slug' => 'vv-a']);

        $this->get('/vv-a/probe')->assertOk()->assertSeeText('vv-a:'.$club->id);
    }

    public function test_unknown_or_inactive_club_gives_404(): void
    {
        Club::factory()->inactive()->create(['slug' => 'slapend']);

        $this->get('/bestaat-niet/probe')->assertNotFound();
        $this->get('/slapend/probe')->assertNotFound();
    }

    public function test_disabled_feature_gives_404(): void
    {
        Club::factory()->create(['slug' => 'zonder', 'features' => []]);
        Club::factory()->create(['slug' => 'met', 'features' => ['duties']]);

        $this->get('/zonder/duties-probe')->assertNotFound();
        $this->get('/met/duties-probe')->assertOk();
    }

    public function test_panel_paths_are_not_taken_as_a_club(): void
    {
        $this->get('/app/login')->assertOk();
        $this->get('/admin/login')->assertOk();
    }
}
