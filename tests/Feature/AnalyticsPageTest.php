<?php

namespace Tests\Feature;

use App\Filament\Platform\Pages\Analytics;
use App\Models\PageView;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_platform_admins_can_see_statistics(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/analytics')->assertForbidden();
        $this->actingAs(User::factory()->platformAdmin()->create())->get('/admin/analytics')
            ->assertOk()->assertSee('Bezoekersstatistieken');
    }

    public function test_statistics_group_views_and_visit_hashes_and_filter_the_period(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        PageView::factory()->count(2)->create(['path' => '/functies', 'visit_hash' => str_repeat('a', 64)]);
        PageView::factory()->create(['path' => '/prijzen', 'viewed_at' => now()->subDays(20)]);
        PageView::factory()->create(['path' => '/old', 'viewed_at' => now()->subDays(100)]);
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');

        Livewire::test(Analytics::class)
            ->assertSee('/functies')->assertSee('/prijzen')->assertDontSee('/old')
            ->assertViewHas('timeline', fn ($rows): bool => $rows->sum('views') === 3 && $rows->sum('visits') === 2)
            ->set('period', '7')->assertDontSee('/prijzen')
            ->assertViewHas('timeline', fn ($rows): bool => $rows->sum('views') === 2 && $rows->sum('visits') === 1);
    }

    public function test_empty_statistics_explain_when_measurements_appear(): void
    {
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');

        Livewire::test(Analytics::class)
            ->assertSee('Nog geen bezoek in deze periode')
            ->assertViewHas('timeline', fn ($rows): bool => $rows->count() === 30);
    }

    public function test_days_run_from_oldest_to_today(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        PageView::factory()->create(['viewed_at' => now()->subDays(6)]);
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');

        Livewire::test(Analytics::class)->set('period', '7')
            ->assertViewHas('timeline', fn ($rows): bool => $rows->first()['date']->isSameDay(today()->subDays(6))
                && $rows->first()['visits'] === 1
                && $rows->last()['date']->isToday());
    }

    public function test_figures_are_compared_with_the_previous_period(): void
    {
        $this->travelTo(today()->setTime(12, 0));
        PageView::factory()->count(2)->create(['visit_hash' => str_repeat('a', 64)]);
        PageView::factory()->create();
        PageView::factory()->create(['viewed_at' => now()->subDays(10)]);
        $this->actingAs(User::factory()->platformAdmin()->create());
        Filament::setCurrentPanel('platform');

        Livewire::test(Analytics::class)->set('period', '7')
            ->assertViewHas('kpis', fn (array $kpis): bool => $kpis[0]['value'] === '2'
                && $kpis[0]['change'] === 1.0
                && $kpis[2]['value'] === '1,5'
                && $kpis[3]['value'] === '50%');
    }
}
