<?php

namespace Tests\Feature\Planner;

use App\Planner\Enums\ChangeRequestKind;
use App\Planner\Enums\ChangeRequestStatus;
use App\Planner\Enums\ContactPreference;
use App\Planner\Enums\DutyType;
use App\Planner\Models\ChangeRequest;
use App\Planner\Models\FixtureMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClubs;
use Tests\TestCase;

class ChangeRequestTest extends TestCase
{
    use InteractsWithClubs, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useClub($this->createClub());
    }

    public function test_contact_details_are_stored_encrypted(): void
    {
        $request = ChangeRequest::factory()->create([
            'name' => 'Jan Jansen', 'email' => 'jan@example.test', 'phone' => '0612345678',
        ]);

        $raw = DB::table('planner_change_requests')->where('id', $request->id)->first();

        $this->assertNotSame('Jan Jansen', $raw->name);
        $this->assertStringNotContainsString('jan@example.test', $raw->email);
        $this->assertSame('jan@example.test', $request->fresh()->email);
        $this->assertSame('0612345678', $request->fresh()->phone);
    }

    public function test_display_texts(): void
    {
        $match = FixtureMatch::factory()->create(['home_team_name' => 'Club HS 1', 'away_team_name' => 'Orion HS 2']);
        $request = ChangeRequest::factory()->create([
            'match_id' => $match->id,
            'contact_preference' => ContactPreference::Whatsapp,
            'phone' => '0612345678',
            'reason' => 'other',
            'reason_other' => 'Bruiloft',
            'proposed_date' => '2026-09-12',
            'proposed_time' => '14:00',
        ]);

        $this->assertTrue($request->isOpen());
        $this->assertSame('WhatsApp (0612345678)', $request->contactText());
        $this->assertSame('Bruiloft', $request->reasonText());
        $this->assertSame('Club HS 1 – Orion HS 2', $request->subjectDescription());
        $this->assertStringContainsString('12-09-2026', (string) $request->proposalText());
        $this->assertStringEndsWith('14:00', (string) $request->proposalText());
        $this->assertSame('warning', ChangeRequestStatus::Open->getColor());
        $this->assertSame([ChangeRequestStatus::Open, ChangeRequestStatus::Parked], ChangeRequestStatus::undecided());
    }

    public function test_a_duty_request_describes_the_duty(): void
    {
        $request = ChangeRequest::factory()->create([
            'kind' => ChangeRequestKind::Duty,
            'duty_type' => DutyType::Referee,
            'duty_description' => 'HS 1 - Orion',
            'match_code' => null,
        ]);

        $this->assertTrue($request->isDuty());
        $this->assertSame('Fluiten · HS 1 - Orion', $request->subjectDescription());
        $this->assertSame('Wedstrijd onbekend', ChangeRequest::factory()->create(['match_code' => null])->matchDescription());
    }
}
