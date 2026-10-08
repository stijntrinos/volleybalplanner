<?php

namespace Tests\Feature\Platform;

use App\Filament\Platform\Resources\Plans\Pages\CreatePlan;
use App\Filament\Platform\Resources\Plans\Pages\EditPlan;
use App\Filament\Platform\Resources\Plans\Pages\ListPlans;
use App\Models\Club;
use App\Models\Plan;
use App\Models\User;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlannerFeature;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');
    }

    public function test_the_three_plans_of_the_pricing_page_exist_after_migrating(): void
    {
        $plans = Plan::query()->ordered()->get();

        $this->assertSame(['basis', 'vereniging', 'groot'], $plans->pluck('slug')->all());
        $this->assertSame([15, 30, null], $plans->map(fn (Plan $plan): ?int => $plan->limit(PlanLimit::Teams))->all());
        $this->assertSame([1, 3, null], $plans->map(fn (Plan $plan): ?int => $plan->limit(PlanLimit::Venues))->all());
        $this->assertSame([3, 6, null], $plans->map(fn (Plan $plan): ?int => $plan->limit(PlanLimit::Admins))->all());
        $this->assertFalse($plans[1]->includes(PlannerFeature::SittingVolleyball));
        $this->assertTrue($plans[2]->includes(PlannerFeature::SittingVolleyball));
    }

    public function test_the_list_shows_the_plans(): void
    {
        Livewire::test(ListPlans::class)
            ->assertCanSeeTableRecords(Plan::all())
            ->assertSee('Onbeperkt');
    }

    public function test_platform_admin_creates_a_plan_with_modules_and_limits(): void
    {
        $undoRepeaterFake = Repeater::fake();

        Livewire::test(CreatePlan::class)
            ->fillForm([
                'name' => 'Toernooi',
                'slug' => 'toernooi',
                'price' => 499,
                'sort_order' => 4,
                'max_teams' => 60,
                'features' => ['duties', 'volunteer_points'],
                'highlights' => [['text' => 'Programmaboekje']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = Plan::query()->where('slug', 'toernooi')->sole();
        $this->assertSame(60, $plan->max_teams);
        $this->assertNull($plan->max_venues);
        $this->assertTrue($plan->includes(PlannerFeature::VolunteerPoints));
        $this->assertSame('€499', $plan->formattedPrice());
        $this->assertSame(['Programmaboekje'], $plan->highlights);

        $undoRepeaterFake();
    }

    public function test_module_dependencies_are_enforced(): void
    {
        Livewire::test(CreatePlan::class)
            ->fillForm(['name' => 'Los', 'slug' => 'los', 'sort_order' => 5, 'features' => ['volunteer_points']])
            ->call('create')
            ->assertHasFormErrors(['features']);
    }

    public function test_a_plan_in_use_cannot_be_deleted(): void
    {
        $plan = Plan::factory()->create();
        Club::factory()->onPlan($plan)->create();

        Livewire::test(EditPlan::class, ['record' => $plan->getRouteKey()])
            ->assertActionHidden(DeleteAction::class);
    }
}
