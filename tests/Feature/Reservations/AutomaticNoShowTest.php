<?php

namespace Tests\Feature\Reservations;

use App\Models\Company;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomaticNoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_only_past_active_reservations_as_no_show(): void
    {
        $company = Company::factory()->create();
        $pastGroup = ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-NOSHOW-0001',
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'confirmed',
        ]);
        $pastSingle = Reservation::factory()->create([
            'company_id' => $company->id,
            'reservation_group_id' => null,
            'check_in' => today()->subDays(2),
            'check_out' => today()->subDay(),
            'status' => 'pending_payment',
        ]);
        $todayGroup = ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-NOSHOW-0002',
            'check_in' => today(),
            'check_out' => today()->addDay(),
            'status' => 'pending_payment',
        ]);
        $futureGroup = ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-NOSHOW-0003',
            'check_in' => today()->addDay(),
            'check_out' => today()->addDays(2),
            'status' => 'confirmed',
        ]);
        $checkedInGroup = ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-NOSHOW-0004',
            'check_in' => today()->subDay(),
            'check_out' => today()->addDay(),
            'status' => 'checked_in',
        ]);

        $this->artisan('reservations:mark-no-shows')
            ->expectsOutputToContain('1 grupos y 1 reservas individuales')
            ->assertSuccessful();

        $this->assertSame('no_show', $pastGroup->refresh()->status);
        $this->assertSame('no_show', $pastSingle->refresh()->status);
        $this->assertSame('pending_payment', $todayGroup->refresh()->status);
        $this->assertSame('confirmed', $futureGroup->refresh()->status);
        $this->assertSame('checked_in', $checkedInGroup->refresh()->status);
    }
}
