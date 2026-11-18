<?php

namespace Tests\Feature\Reports;

use App\Models\AccountStatement;
use App\Models\Company;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProjectedCollectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_projection_uses_pending_balances_once_and_only_for_active_company(): void
    {
        Permission::findOrCreate('reports.view');
        $stay = Stay::factory()->create();
        $user = User::factory()->create(['company_id' => $stay->company_id]);
        $user->givePermissionTo('reports.view');
        AccountStatement::create(['company_id' => $stay->company_id, 'stay_id' => $stay->id, 'currency' => 'USD', 'subtotal' => 100, 'payments_total' => 30, 'balance' => 70, 'status' => 'partial']);
        $group = ReservationGroup::factory()->create(['company_id' => $stay->company_id, 'guest_name' => 'Reserva agrupada', 'balance_amount' => 200]);
        Reservation::factory()->create(['company_id' => $stay->company_id, 'reservation_group_id' => $group->id, 'guest_name' => 'No duplicar']);
        ReservationGroup::factory()->create(['company_id' => $stay->company_id, 'status' => 'checked_in', 'guest_name' => 'Ya ingresó']);
        ReservationGroup::factory()->create(['company_id' => Company::factory()->create()->id, 'guest_name' => 'Otra empresa']);
        $this->withoutVite();

        $this->actingAs($user)->get(route('reports.projected.index'))->assertOk()
            ->assertSee('Reserva agrupada')->assertSee('70.00 USD')
            ->assertDontSee('No duplicar')->assertDontSee('Ya ingresó')->assertDontSee('Otra empresa');
    }

    public function test_final_date_filters_reference_dates_inclusively(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-12-01'));
        Permission::findOrCreate('reports.view');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('reports.view');
        ReservationGroup::factory()->create(['company_id' => $company->id, 'guest_name' => 'Dentro del plazo', 'check_in' => '2026-12-10']);
        ReservationGroup::factory()->create(['company_id' => $company->id, 'guest_name' => 'Fuera del plazo', 'check_in' => '2026-12-11']);
        $this->withoutVite();

        $this->actingAs($user)->get(route('reports.projected.index', ['to' => '2026-12-10']))
            ->assertOk()->assertSee('Dentro del plazo')->assertDontSee('Fuera del plazo')
            ->assertSee('Reserva · 1 registros');
    }

    public function test_report_requires_permission(): void
    {
        $user = User::factory()->create(['company_id' => Company::factory()]);
        $this->actingAs($user)->get(route('reports.projected.index'))->assertForbidden();
    }
}
