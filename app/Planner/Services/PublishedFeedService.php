<?php

namespace App\Planner\Services;

use App\Planner\Enums\MatchType;
use App\Planner\Enums\ScheduleVersionStatus;
use App\Planner\Enums\VolleystarsRole;
use App\Planner\Features\PlannerFeature;
use App\Planner\Models\FixtureMatch;
use App\Planner\Models\ScheduleVersion;
use App\Support\Tenancy\CurrentClub;
use Carbon\CarbonImmutable;
use DOMDocument;
use Illuminate\Support\Collection;

/** The public RSS feeds of the current club's published planning (module rss_feeds). */
class PublishedFeedService
{
    /** Prefix of the planner-specific elements in the central feed. */
    public const NAMESPACE_PREFIX = 'planner';

    /** @return Collection<int, FixtureMatch> the published home matches, optionally of one date */
    public function matches(?string $date = null): Collection
    {
        $version = $this->publishedVersion();
        if (!$version) {
            return collect();
        }

        return $version->matches()->where('match_type', MatchType::Home)
            ->whereNotNull('date')->whereNotNull('time')
            ->when($date, fn ($query) => $query->whereDate('date', $date))
            ->with(['venue', 'court'])->orderBy('date')->orderBy('time')->get();
    }

    /** @param Collection<int, FixtureMatch> $matches */
    public function xml(string $title, string $description, Collection $matches): string
    {
        $items = $matches->map(function (FixtureMatch $match): string {
            $time = substr((string) $match->time, 0, 5);
            // Only a recorded original that differs counts as a change; a match
            // that was never moved has no original_* values at all.
            $changed = ($match->original_date && $match->original_date->toDateString() !== $match->date?->toDateString())
                || ($match->original_time && substr($match->original_time, 0, 5) !== $time)
                || ($match->original_venue && $match->original_venue !== $match->venue?->name)
                || ($match->original_court && $match->original_court !== $match->court?->name);
            $itemTitle = "{$match->date->toDateString()} {$time} · {$match->home_team_name} vs {$match->away_team_name} — {$match->venue?->name} {$match->court?->name}"
                .($changed ? ' ['.__('GEWIJZIGD').']' : '');
            $details = __('Wedstrijdcode').": {$match->match_code} | ".__('Poule').': '.($match->pool ?: '-')
                .' | '.__('Status').': '.($changed ? __('gewijzigd') : __('gepland'));

            return '<item><title>'.$this->escape($itemTitle).'</title><description>'.$this->escape($details).'</description><pubDate>'
                .$match->date->copy()->setTimeFromTimeString($time)->toRfc2822String().'</pubDate><guid isPermaLink="false">match-'.$match->id.'</guid></item>';
        })->implode('');

        return $this->formatXml('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'.$this->escape($title).'</title><link>'
            .$this->escape($this->clubUrl()).'</link><description>'.$this->escape($description).'</description><language>nl-NL</language>'.$items.'</channel></rss>');
    }

    /**
     * The central feed: every upcoming home match, plus hall duties and
     * Volleystars tasks when those modules are on, with the details as
     * planner:* elements.
     */
    public function centralXml(): string
    {
        $club = app(CurrentClub::class)->require();
        $title = __(':club wedstrijdplanning', ['club' => $club->name]);
        $version = $this->publishedVersion();
        if (!$version) {
            return $this->rssDocument($title, '', null);
        }

        $from = today()->toDateString();
        $items = collect();
        $matches = $version->matches()->where('match_type', MatchType::Home)->whereDate('date', '>=', $from)
            ->whereNotNull('time')->with(['venue', 'court'])->get();
        foreach ($matches as $match) {
            $items->push([
                'type' => 'match', 'id' => $match->id, 'date' => $match->date->toDateString(),
                'time' => substr((string) $match->time, 0, 5), 'venue' => $match->venue?->name,
                'court' => $match->court?->name, 'title' => "{$match->home_team_name} – {$match->away_team_name}",
                'fields' => [
                    'referee' => $match->referee, 'scorer' => $match->scorer,
                    'match_code' => $match->match_code, 'home_team' => $match->home_team_name,
                    'away_team' => $match->away_team_name,
                ],
            ]);
        }
        if ($club->hasFeature(PlannerFeature::Duties)) {
            foreach ($version->hallDuties()->whereDate('date', '>=', $from)->with(['venue', 'team'])->get() as $duty) {
                $items->push([
                    'type' => 'hall_duty', 'id' => $duty->id, 'date' => $duty->date->toDateString(),
                    'time' => substr((string) $duty->start_time, 0, 5), 'venue' => $duty->venue->name,
                    'court' => null, 'title' => __('Zaaldienst').' '.$duty->team->code,
                    'fields' => [
                        'start_time' => substr((string) $duty->start_time, 0, 5),
                        'end_time' => substr((string) $duty->end_time, 0, 5),
                        'team' => $duty->team->code,
                    ],
                ]);
            }
        }
        if ($club->hasFeature(PlannerFeature::Volleystars)) {
            $blocks = $version->volleystarsBlocks()->whereDate('date', '>=', $from)->with(['venue', 'tasks.team', 'tasks.court'])->get();
            foreach ($blocks as $block) {
                foreach ($block->tasks as $task) {
                    [$start, $end] = match ($task->role) {
                        VolleystarsRole::Scoring => [$block->scoring_start, $block->scoring_end],
                        VolleystarsRole::Teardown => [$block->teardown_start, $block->teardown_end],
                        default => [$block->setup_start, $block->setup_end],
                    };
                    $role = $task->role->getLabel();
                    $items->push([
                        'type' => 'volleystars', 'id' => $task->id, 'date' => $block->date->toDateString(),
                        'time' => substr((string) $start, 0, 5), 'venue' => $block->venue->name,
                        'court' => $task->court?->name, 'title' => 'VolleyStars · '.$role,
                        'fields' => [
                            'start_time' => substr((string) $start, 0, 5), 'end_time' => substr((string) $end, 0, 5),
                            'team' => $task->team?->code, 'description' => $role,
                        ],
                    ]);
                }
            }
        }

        $xmlItems = $items->sortBy(fn (array $item): string => $item['date'].' '.$item['time'].' '.$item['venue'].' '.$item['court'])
            ->map(fn (array $item): string => $this->centralItem($item, $version->id))->implode('');

        return $this->rssDocument($title, $xmlItems, $version);
    }

    /** The namespace URI of the planner:* elements. */
    public static function namespaceUri(): string
    {
        return rtrim((string) config('app.url'), '/').'/rss/ns';
    }

    /** @param array<string, mixed> $item */
    private function centralItem(array $item, int $versionId): string
    {
        $prefix = self::NAMESPACE_PREFIX;
        $element = fn (string $name, ?string $value): string => "<{$prefix}:{$name}>".$this->escape($value)."</{$prefix}:{$name}>";
        $dateTime = CarbonImmutable::parse($item['date'].' '.$item['time'], 'Europe/Amsterdam');
        $fields = $element('date', CarbonImmutable::parse($item['date'])->format('d-m-Y'))
            .$element('time', $item['time'])
            .$element('venue', $item['venue'])
            .($item['court'] ? $element('court', $item['court']) : '');
        foreach ($item['fields'] as $name => $value) {
            if ($value !== null && $value !== '') {
                $fields .= $element($name, (string) $value);
            }
        }

        return '<item><title>'.$this->escape($item['title']).'</title><link>'.$this->escape($this->clubUrl()).'</link><pubDate>'.$dateTime->utc()->toRfc2822String().'</pubDate>'
            .'<guid isPermaLink="false">'.$this->escape($item['type'].'-'.$item['id'].'-'.$versionId).'</guid><category>'.$this->escape($item['type']).'</category>'
            .$element('type', $item['type']).$fields.'</item>';
    }

    private function rssDocument(string $title, string $items, ?ScheduleVersion $version): string
    {
        $prefix = self::NAMESPACE_PREFIX;
        $metadata = $version
            ? "<{$prefix}:scheduleVersion>".$this->escape($version->name)."</{$prefix}:scheduleVersion>"
                ."<{$prefix}:scheduleVersionId>{$version->id}</{$prefix}:scheduleVersionId>"
                ."<{$prefix}:scheduleStatus>".$this->escape($version->status->value)."</{$prefix}:scheduleStatus>"
                ."<{$prefix}:publishedAt>".$this->escape($version->published_at?->toIso8601String())."</{$prefix}:publishedAt>"
            : '';

        return $this->formatXml('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:'.$prefix.'="'.$this->escape(self::namespaceUri()).'"><channel><title>'
            .$this->escape($title).'</title><link>'.$this->escape($this->clubUrl()).'</link><language>nl-NL</language>'.$metadata.$items.'</channel></rss>');
    }

    private function publishedVersion(): ?ScheduleVersion
    {
        return ScheduleVersion::query()->where('status', ScheduleVersionStatus::Published)->first();
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function formatXml(string $xml): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;
        $document->loadXML($xml);

        return (string) $document->saveXML();
    }

    /** The club's public matches page, used as the feed and item link. */
    private function clubUrl(): string
    {
        $club = app(CurrentClub::class)->get();

        return $club ? route('planner.public.matches', ['club' => $club->slug]) : url('/');
    }
}
