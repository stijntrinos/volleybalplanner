<?php

namespace App\Planner\Filament\Pages\Concerns;

use App\Planner\Models\Activity;
use App\Planner\Models\Court;
use App\Planner\Models\ScheduleVersion;
use App\Planner\Models\Venue;
use App\Support\Tenancy\CurrentClub;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * A loose card on the day, such as a court that is taken for a skills
 * training. As long as the card is there, no match can be planned on that
 * court in that time slot. Without a court it blocks the whole venue.
 *
 * @property-read Collection<int, string> $activityVenues
 * @property-read Collection<int, string> $activityCourts
 */
trait ManagesActivityModal
{
    /** null = closed, 0 = new activity, otherwise the activity being edited. */
    public ?int $modalActivityId = null;

    public string $modalActivityTitle = '';

    public ?int $modalActivityVenueId = null;

    /** Empty means the whole venue. */
    public ?int $modalActivityCourtId = null;

    public string $modalActivityStartTime = '';

    public string $modalActivityEndTime = '';

    public string $modalActivityNotes = '';

    public function openNewActivity(): void
    {
        abort_unless(auth()->user()?->can('Create:Activity'), 403);
        if (!$this->draftVersionForActivity()) {
            Notification::make()->title(__('Selecteer eerst een conceptplanning'))->warning()->send();

            return;
        }

        $this->resetValidation();
        $this->modalActivityId = 0;
        $this->modalActivityTitle = '';
        $defaultVenueId = collect($this->day['default_venues'] ?? [])->first();
        $this->modalActivityVenueId = $this->activityVenues->has($defaultVenueId)
            ? (int) $defaultVenueId
            : $this->activityVenues->keys()->first();
        $this->modalActivityCourtId = null;
        $this->modalActivityStartTime = '';
        $this->modalActivityEndTime = '';
        $this->modalActivityNotes = '';
    }

    public function openActivity(int $activityId): void
    {
        abort_unless(auth()->user()?->can('View:Activity'), 403);
        $activity = Activity::query()->where('version_id', $this->versionId)->findOrFail($activityId);

        $this->resetValidation();
        $this->modalActivityId = $activity->id;
        $this->modalActivityTitle = (string) $activity->title;
        $this->modalActivityVenueId = $activity->venue_id;
        $this->modalActivityCourtId = $activity->court_id;
        $this->modalActivityStartTime = substr((string) $activity->start_time, 0, 5);
        $this->modalActivityEndTime = substr((string) $activity->end_time, 0, 5);
        $this->modalActivityNotes = (string) $activity->notes;
    }

    public function closeActivity(): void
    {
        $this->reset([
            'modalActivityId', 'modalActivityTitle', 'modalActivityVenueId', 'modalActivityCourtId',
            'modalActivityStartTime', 'modalActivityEndTime', 'modalActivityNotes',
        ]);
        $this->resetValidation();
    }

    public function updatedModalActivityVenueId(): void
    {
        $this->modalActivityCourtId = null;
    }

    public function saveActivity(): void
    {
        $isNew = $this->modalActivityId === 0;
        abort_unless(auth()->user()?->can($isNew ? 'Create:Activity' : 'Update:Activity'), 403);
        $version = $this->draftVersionForActivity();
        if (!$version) {
            Notification::make()->title(__('Deze planning is alleen-lezen'))->danger()->send();

            return;
        }

        $clubId = app(CurrentClub::class)->id();
        $data = $this->validate([
            'modalActivityTitle' => ['required', 'string', 'max:255'],
            'modalActivityVenueId' => ['required', 'integer', Rule::exists('planner_venues', 'id')
                ->where('club_id', $clubId)->where('is_active', true)->where('is_managed', true)],
            'modalActivityCourtId' => ['nullable', 'integer', Rule::exists('planner_courts', 'id')
                ->where('club_id', $clubId)->where('venue_id', (int) $this->modalActivityVenueId)],
            'modalActivityStartTime' => ['required', 'date_format:H:i'],
            'modalActivityEndTime' => ['required', 'date_format:H:i', 'after:modalActivityStartTime'],
            'modalActivityNotes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'modalActivityTitle' => __('naam'), 'modalActivityVenueId' => __('sporthal'),
            'modalActivityCourtId' => __('veld'), 'modalActivityStartTime' => __('begintijd'),
            'modalActivityEndTime' => __('eindtijd'),
        ]);

        $values = [
            'title' => $data['modalActivityTitle'],
            'venue_id' => $data['modalActivityVenueId'],
            'court_id' => $data['modalActivityCourtId'] ?? null,
            'start_time' => $data['modalActivityStartTime'],
            'end_time' => $data['modalActivityEndTime'],
            'notes' => ($data['modalActivityNotes'] ?? null) ?: null,
        ];
        if ($isNew) {
            $activity = Activity::query()->create($values + ['version_id' => $version->id, 'date' => $this->date]);
        } else {
            $activity = Activity::query()->where('version_id', $version->id)->findOrFail($this->modalActivityId);
            $activity->update($values);
        }
        activity('planner-planning')->performedOn($activity)->causedBy(auth()->id())
            ->log($isNew ? 'Activity added via home matches' : 'Activity edited via home matches');

        $this->closeActivity();
        $this->load();
        Notification::make()->title($isNew ? __('Activiteit toegevoegd') : __('Activiteit opgeslagen'))->success()->send();
    }

    public function deleteActivity(): void
    {
        abort_unless(auth()->user()?->can('Delete:Activity'), 403);
        $version = $this->draftVersionForActivity();
        if (!$version || !$this->modalActivityId) {
            return;
        }

        $activity = Activity::query()->where('version_id', $version->id)->findOrFail($this->modalActivityId);
        activity('planner-planning')->performedOn($activity)->causedBy(auth()->id())
            ->withProperties($activity->toArray())->log('Activity deleted via home matches');
        $activity->delete();
        $this->closeActivity();
        $this->load();
        Notification::make()->title(__('Activiteit verwijderd'))->success()->send();
    }

    /** @return Collection<int, string> */
    public function getActivityVenuesProperty(): Collection
    {
        return Venue::query()->where('is_active', true)->where('is_managed', true)
            ->inPlanningOrder()->pluck('name', 'id');
    }

    /** @return Collection<int, string> */
    public function getActivityCourtsProperty(): Collection
    {
        return $this->modalActivityVenueId
            ? Court::query()->where('venue_id', $this->modalActivityVenueId)->where('is_active', true)
                ->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')
            : collect();
    }

    private function draftVersionForActivity(): ?ScheduleVersion
    {
        $version = $this->versionId ? ScheduleVersion::query()->find($this->versionId) : null;

        return $version?->isDraft() ? $version : null;
    }
}
