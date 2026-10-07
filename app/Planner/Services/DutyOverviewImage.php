<?php

namespace App\Planner\Services;

use App\Planner\Features\PlannerFeature;
use App\Planner\Support\RendersWithChrome;
use App\Support\Tenancy\CurrentClub;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * The duty overview as an image, to share in a team app or on social media.
 * Chrome only photographs the sheet, so the height grows with the number of
 * teams instead of a fixed size with empty space below.
 *
 * Rows come from DutyTallyService::perTeam() plus a 'status_label' (the
 * "Stand" column). used/earned/percentage only exist with volunteer points,
 * volleystars only with the VolleyStars module.
 */
class DutyOverviewImage
{
    use RendersWithChrome;

    private const WIDTH = 1200;

    /** Twice as sharp: the table is often zoomed in on a phone. */
    private const SCALE = 2;

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int|float>  $totals
     */
    public function png(array $rows, array $totals, string $period): string
    {
        try {
            return $this->browser($this->html($rows, $totals, $period))
                ->windowSize(self::WIDTH, 800)
                ->deviceScaleFactor(self::SCALE)
                ->setScreenshotType('png')
                ->select('.sheet')
                ->screenshot();
        } catch (CouldNotTakeBrowsershot|ProcessFailedException $exception) {
            throw new RuntimeException(
                __('Het takenoverzicht kon niet worden gerenderd: :cause', ['cause' => $this->cause($exception)]),
                previous: $exception,
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int|float>  $totals
     */
    public function html(array $rows, array $totals, string $period): string
    {
        $club = app(CurrentClub::class)->get();

        return View::make('planner::exports.duty-overview', [
            'rows' => $rows,
            'totals' => $totals,
            'period' => $period,
            'width' => self::WIDTH,
            'branding' => $this->branding(),
            'fonts' => $this->fonts(),
            'showVolleystars' => (bool) $club?->hasFeature(PlannerFeature::Volleystars),
            'showPoints' => (bool) $club?->hasFeature(PlannerFeature::VolunteerPoints),
        ])->render();
    }
}
