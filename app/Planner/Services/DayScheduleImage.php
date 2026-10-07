<?php

namespace App\Planner\Services;

use App\Planner\Models\Court;
use App\Planner\PlannerPlugin;
use App\Planner\Support\DateFormat;
use App\Planner\Support\DayScheduleGrid;
use App\Planner\Support\RendersWithChrome;
use App\Planner\Support\TextMeasure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Browsershot\Exceptions\CouldNotTakeBrowsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * The day schedule as an image.
 *
 * A Blade template photographed by Chrome, so the design lives in CSS
 * instead of in coordinates.
 *
 * Everything the page needs goes along as a data URI. Chrome opens the HTML
 * from a temporary file and could not follow a path to storage or public.
 */
class DayScheduleImage
{
    use RendersWithChrome;

    /** The width of the layout in CSS pixels; the sheet is then scaled into the frame. */
    private const BASE_WIDTH = 1240;

    /**
     * The image always comes out at 1920x1080: the size of a screen and of
     * social media. The sheet is scaled into that frame, so the layout below
     * keeps calculating in its own sizes.
     */
    private const TARGET_WIDTH = 1920;

    private const TARGET_HEIGHT = 1080;

    /**
     * A day schedule is taller than wide, so the height determines the scale
     * and leaves width in the frame. The sheet stretches until that space is
     * filled: the columns get wider, the font sizes stay. This upper bound is
     * for the exception -- a match day with two start times would otherwise be
     * stretched into a strip.
     */
    private const MAX_STRETCH = 3.0;

    /** High enough to keep the edges of the letters from fraying. */
    private const JPEG_QUALITY = 92;

    /** From four courts on the sheet grows along, otherwise the cards become unreadable. */
    private const COLUMN_WIDTH = 250;

    private const MARGIN = 24;

    private const GAP = 17;

    /** Rows sit closer together than columns. */
    private const ROW_GAP = 11;

    private const HALL_DUTY_WIDTH = 219;

    private const MINIMUM_ROW_HEIGHT = 117;

    /** The height of the bar above a card; the tab with the time drops below it. */
    private const BAR_HEIGHT = 13;

    /** The horizontal inner space of a card, left and right together. */
    private const CARD_PADDING = 20;

    /**
     * The parts of a column header. These sizes go both to the stylesheet and
     * to the font size calculation; adjusting them separately produced a
     * clipped "ZAALWACHT".
     */
    private const HEADER = [
        'padding' => 20,
        'number' => 54,
        'gap' => 26,
        'label' => 39,
    ];

    private const DUTY_HEADER = [
        'padding' => 12,
        'icon' => 48,
        'gap' => 12,
        'label' => 25,
    ];

    /**
     * @param  array<string, mixed>  $data  keys: date, matches, hall_duties, locker_rooms, settings, footer_text, show_duties, show_locker_rooms
     * @param  Collection<int, Court>  $courts
     */
    public function jpg(array $data, Collection $courts): string
    {
        $html = $this->html($data, $courts);

        try {
            return $this->browser($html)
                ->windowSize(self::TARGET_WIDTH, self::TARGET_HEIGHT)
                ->deviceScaleFactor(1)
                ->setScreenshotType('jpeg', self::JPEG_QUALITY)
                ->waitUntilNetworkIdle()
                // The page stretches and scales itself into the frame; only
                // when that is done is the sheet in place.
                ->waitForFunction('window.dayScheduleScaled === true')
                ->screenshot();
        } catch (CouldNotTakeBrowsershot|ProcessFailedException $exception) {
            // A silent empty download is worse than a message. Chrome's
            // cause comes along, so the log line does not point at node.
            throw new RuntimeException(
                __('Het dagschema kon niet worden gerenderd: :cause', ['cause' => $this->cause($exception)]),
                previous: $exception,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Collection<int, Court>  $courts
     */
    public function html(array $data, Collection $courts): string
    {
        $showDuties = (bool) ($data['show_duties'] ?? true);
        $grid = new DayScheduleGrid($courts, $data['matches'], $showDuties ? $data['hall_duties'] : collect());
        $dutyWidth = $showDuties ? self::HALL_DUTY_WIDTH : 0;
        $width = $this->width($courts, $dutyWidth);
        $footer = PlannerPlugin::renderScheduleFooter();

        return View::make('planner::exports.day-schedule', [
            'grid' => $grid,
            'branding' => $this->branding($data['settings'] ?? null),
            'venueName' => $this->venueName($data['matches']),
            'date' => DateFormat::date($data['date']),
            'footerText' => ($data['footer_text'] ?? null) ?: ($data['settings']?->schedule_footer ?: __('Graag melden bij de zaalwacht!')),
            'scheduleFooter' => $footer instanceof Htmlable ? $footer->toHtml() : $footer,
            'showDuties' => $showDuties,
            'showLockerRooms' => (bool) ($data['show_locker_rooms'] ?? false),
            'lockerRooms' => $data['locker_rooms']['assignments'] ?? [],
            'width' => $width,
            'maxWidth' => (int) round($width * self::MAX_STRETCH),
            'targetWidth' => self::TARGET_WIDTH,
            'targetHeight' => self::TARGET_HEIGHT,
            'hallDutyWidth' => $dutyWidth,
            'minimumRowHeight' => self::MINIMUM_ROW_HEIGHT,
            'barHeight' => self::BAR_HEIGHT,
            'margin' => self::MARGIN,
            'gap' => self::GAP,
            'rowGap' => self::ROW_GAP,
            'fonts' => $this->fonts(),
            'svg' => fn (string $name): string => $this->svg($name),
            'textSize' => fn (string $text, int $columnSpan, int $start): int => TextMeasure::fitting(
                $text,
                $this->cardWidth($courts, $columnSpan, $dutyWidth),
                $start,
            ),
            'header' => self::HEADER,
            'dutyHeader' => self::DUTY_HEADER,
            // The headers are in capitals through text-transform; measuring
            // must therefore also use capitals, which are wider.
            'courtHeaderSize' => fn (string $name): int => TextMeasure::fitting(
                Str::upper(__('Veld').' '.$name),
                $this->columnWidth($courts, $dutyWidth) - (2 * self::HEADER['padding']) - self::HEADER['number'] - self::HEADER['gap'],
                self::HEADER['label'],
            ),
            'dutyHeaderSize' => TextMeasure::fitting(
                Str::upper(__('Zaalwacht')),
                self::HALL_DUTY_WIDTH - (2 * self::DUTY_HEADER['padding']) - self::DUTY_HEADER['icon'] - self::DUTY_HEADER['gap'],
                self::DUTY_HEADER['label'],
            ),
        ])->render();
    }

    /**
     * The space for text in a card, in CSS pixels. A card over two courts
     * gets the gap between them as well.
     *
     * @param  Collection<int, Court>  $courts
     */
    private function cardWidth(Collection $courts, int $columnSpan, int $dutyWidth): int
    {
        $column = $this->columnWidth($courts, $dutyWidth);

        return ($column * $columnSpan) + (($columnSpan - 1) * self::GAP) - self::CARD_PADDING;
    }

    /**
     * The width of one court column. The columns are 1fr, so what the grid
     * has left after the margins, the hall duty column and the gaps is
     * divided evenly.
     *
     * @param  Collection<int, Court>  $courts
     */
    private function columnWidth(Collection $courts, int $dutyWidth): int
    {
        $columns = max(1, $courts->count());
        $available = $this->width($courts, $dutyWidth) - (2 * self::MARGIN) - $dutyWidth - ($columns * self::GAP);

        return (int) floor($available / $columns);
    }

    /**
     * The sheet grows with the number of courts. At a fixed width the columns
     * became an unreadable strip at eight or nine courts.
     *
     * @param  Collection<int, Court>  $courts
     */
    private function width(Collection $courts, int $dutyWidth): int
    {
        $needed = (2 * self::MARGIN) + $dutyWidth + (max(1, $courts->count()) * (self::COLUMN_WIDTH + self::GAP));

        return max(self::BASE_WIDTH, $needed);
    }

    /**
     * Without a venue filter several venues are on the sheet; then the name
     * of the first match would be a misleading heading.
     *
     * @param  Collection<int, mixed>  $matches
     */
    private function venueName(Collection $matches): string
    {
        $venues = $matches->map(fn ($match) => $match->venue?->name)->filter()->unique();
        $name = Str::upper((string) ($venues->count() === 1 ? $venues->first() : $venues->implode(' / ')));
        $prefix = Str::upper(__('Sporthal'));

        return Str::startsWith($name, $prefix) ? $name : Str::squish($prefix.' '.$name);
    }

    private function svg(string $name): string
    {
        $path = resource_path("svg/planner/{$name}.svg");

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
