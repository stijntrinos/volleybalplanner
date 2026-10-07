<?php

namespace Tests\Feature\Platform;

use App\Models\Club;
use App\Support\Tenancy\BelongsToClub;
use App\Support\Tenancy\CurrentClub;
use App\Support\Tenancy\NoCurrentClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenancyProbe extends Model
{
    use BelongsToClub;

    protected $table = 'tenancy_probes';

    protected $fillable = ['label', 'club_id'];
}

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('tenancy_probes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('club_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->timestamps();
        });
    }

    public function test_records_are_scoped_to_the_current_club_and_filled_on_create(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $current = app(CurrentClub::class);

        $current->run($a, fn () => TenancyProbe::create(['label' => 'a']));
        $current->run($b, fn () => TenancyProbe::create(['label' => 'b']));

        $this->assertSame(['a'], $current->run($a, fn () => TenancyProbe::pluck('label')->all()));
        $this->assertSame(['b'], $current->run($b, fn () => TenancyProbe::pluck('label')->all()));
        $this->assertSame($b->id, TenancyProbe::withoutGlobalScope('club')->where('label', 'b')->value('club_id'));
    }

    public function test_querying_without_a_current_club_throws(): void
    {
        $this->expectException(NoCurrentClub::class);

        TenancyProbe::count();
    }

    public function test_run_restores_the_previous_club_even_after_an_exception(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();
        $current = app(CurrentClub::class);
        $current->set($a);

        try {
            $current->run($b, fn () => throw new RuntimeException('boom'));
        } catch (RuntimeException) {
        }

        $this->assertTrue($current->get()->is($a));
    }

    public function test_an_explicit_club_id_on_create_is_not_overwritten(): void
    {
        [$a, $b] = Club::factory()->count(2)->create();

        $probe = app(CurrentClub::class)->run($a, fn () => TenancyProbe::create(['label' => 'x', 'club_id' => $b->id]));

        $this->assertSame($b->id, $probe->club_id);
    }
}
