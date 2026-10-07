<?php

namespace App\Planner\Http\Controllers;

use App\Planner\Features\PlannerFeature;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Contracts\View\View;

/** A small public landing page per club that links to the enabled public pages. */
class ClubHomeController
{
    public function __invoke(CurrentClub $currentClub): View
    {
        $club = $currentClub->require();
        $embed = request()->boolean('embed') ? ['embed' => 1] : [];
        $link = fn (string $name, array $parameters = []): string => route('planner.public.'.$name, ['club' => $club, ...$parameters, ...$embed]);

        $pages = [[
            'title' => __('Wedstrijden & diensten'),
            'description' => __('Alle komende thuiswedstrijden en diensten uit de gepubliceerde planning.'),
            'url' => $link('matches'),
        ]];

        if ($club->hasFeature(PlannerFeature::ChangeRequests)) {
            $pages[] = [
                'title' => __('Wedstrijd verzetten'),
                'description' => __('Vraag wedstrijdzaken om een wedstrijd van je team te verplaatsen.'),
                'url' => $link('match-change-request'),
            ];

            if ($club->hasFeature(PlannerFeature::Duties)) {
                $pages[] = [
                    'title' => __('FTZ-dienst wijzigen'),
                    'description' => __('Vraag een wijziging aan voor een fluit-, tel- of zaaldienst van je team.'),
                    'url' => $link('duty-change-request'),
                ];
            }
        }

        $feeds = $club->hasFeature(PlannerFeature::RssFeeds) ? [
            ['title' => __('Alle wedstrijden en diensten'), 'url' => route('planner.public.rss.central', ['club' => $club])],
            ['title' => __('Thuiswedstrijden'), 'url' => route('planner.public.rss.home', ['club' => $club])],
        ] : [];

        return view('planner::public.home', [
            'club' => $club,
            'pages' => $pages,
            'feeds' => $feeds,
        ]);
    }
}
