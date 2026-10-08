<?php

namespace App\Filament\Club\Pages;

use App\Models\Club;
use App\Models\Plan;
use App\Models\User;
use App\Planner\Features\PlanLimit;
use App\Planner\Features\PlanLimits;
use App\Planner\Features\PlannerFeature;
use App\Planner\Support\ClubRole;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * The club's own view of its subscription: plan, usage of the limits and
 * which modules it has. Locked modules open the upgrade prompt.
 */
class Subscription extends Page
{
    protected static ?string $slug = 'abonnement';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?int $navigationSort = 91;

    protected string $view = 'filament.club.pages.subscription';

    public static function getNavigationLabel(): string
    {
        return __('Abonnement');
    }

    public function getTitle(): string
    {
        return __('Abonnement');
    }

    public static function canAccess(): bool
    {
        $club = Filament::getTenant();
        $user = auth()->user();

        return $club instanceof Club
            && $user instanceof User
            && ($user->isPlatformAdmin() || $user->hasClubRole($club, ClubRole::ClubAdmin));
    }

    private static function period(Club $club): ?string
    {
        $date = fn ($day): string => $day->translatedFormat('j F Y');

        return match (true) {
            $club->trial_ends_at !== null && $club->subscription_starts_at === null => __('Proefperiode tot :date', ['date' => $date($club->trial_ends_at)]),
            $club->subscription_ends_at !== null => __('Loopt van :from tot :to', [
                'from' => $club->subscription_starts_at ? $date($club->subscription_starts_at) : '…',
                'to' => $date($club->subscription_ends_at),
            ]),
            $club->subscription_starts_at !== null => __('Sinds :date, doorlopend', ['date' => $date($club->subscription_starts_at)]),
            default => null,
        };
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $club = Filament::getTenant();
        assert($club instanceof Club);
        $club->loadMissing('plan');

        return [
            'plan' => $club->plan,
            'period' => self::period($club),
            // On the largest plan there is nothing to upgrade to.
            'canUpgrade' => $club->plan ? $club->plan->nextPlan() !== null : Plan::query()->exists(),
            'limits' => array_map(fn (PlanLimit $limit): array => [
                'limit' => $limit,
                'used' => PlanLimits::count($limit),
                'max' => $club->planLimit($limit),
            ], PlanLimit::cases()),
            'modules' => array_map(fn (PlannerFeature $feature): array => [
                'feature' => $feature,
                'included' => $club->hasFeature($feature),
                'upgrade' => $club->hasFeature($feature) ? null : $club->upgradePlanName($feature),
            ], PlannerFeature::cases()),
        ];
    }
}
