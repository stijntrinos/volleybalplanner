<?php

namespace App\Support\Demo;

use App\Models\Club;
use App\Models\ContactRequest;
use App\Models\Plan;
use App\Models\User;
use App\Planner\Support\ClubRole;
use App\Support\Tenancy\ClubInvitations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Turns a demo request from the public site into a working demo club: a
 * club on the largest plan with a trial, filled with demo data, and an
 * invitation for the requester to sign in as club admin.
 *
 * A demo is a normal club with is_demo set. After the trial it turns
 * read-only like any club; platform:prune-demo-clubs deletes it later,
 * unless a platform admin converted it into a customer first.
 */
final class DemoClubs
{
    public function __construct(
        private readonly DemoClubBuilder $builder,
        private readonly ClubInvitations $invitations,
    ) {}

    /**
     * The demo club for the request; made once, later calls return the same club.
     *
     * @throws InvalidArgumentException when the request is not a demo request
     */
    public function createFor(ContactRequest $request, ?User $inviter = null): Club
    {
        if (!$request->isDemoRequest()) {
            throw new InvalidArgumentException('Only a demo request can get a demo club.');
        }

        return DB::transaction(function () use ($request, $inviter): Club {
            // Locked, so a double click cannot make two demos.
            $locked = ContactRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            $existing = $locked->demoClub;
            if ($existing instanceof Club) {
                $request->setRawAttributes($locked->getAttributes(), true);

                return $existing;
            }

            $name = $this->organisation($locked);
            $club = Club::query()->create([
                'name' => __(':name (demo)', ['name' => $name]),
                'slug' => $this->uniqueSlug($name),
                'is_demo' => true,
                'plan_id' => $this->largestPlan()?->getKey(),
                'trial_ends_at' => today()->addDays((int) config('planner.demo.trial_days', 14)),
            ]);

            $this->builder->fill($club);
            $this->invitations->invite($club, $locked->name, $locked->email, ClubRole::ClubAdmin, $inviter);

            $locked->forceFill(['demo_club_id' => $club->getKey(), 'handled_at' => $locked->handled_at ?? now()])->save();
            $request->setRawAttributes($locked->getAttributes(), true);
            $request->setRelation('demoClub', $club);

            return $club;
        });
    }

    private function organisation(ContactRequest $request): string
    {
        $organisation = trim($request->club);

        return $organisation !== '' ? $organisation : trim($request->name);
    }

    /** "demo-<organisation>-<4 random>", short enough for a URL. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 40, '') ?: 'club';

        do {
            $slug = 'demo-'.trim($base, '-').'-'.Str::lower(Str::random(4));
        } while (Club::query()->where('slug', $slug)->exists());

        return $slug;
    }

    /** The plan with every module, so the demo shows everything. */
    private function largestPlan(): ?Plan
    {
        return Plan::query()->where('slug', 'groot')->first()
            ?? Plan::query()->orderByDesc('sort_order')->orderByDesc('id')->first();
    }
}
