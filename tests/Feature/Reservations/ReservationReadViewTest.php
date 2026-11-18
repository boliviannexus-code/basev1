<?php

namespace Tests\Feature\Reservations;

use App\Models\Company;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationReadViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_actions_remain_limited_by_permissions_in_the_top_panel(): void
    {
        foreach (['reservations.view', 'reservations.manage', 'occupancy.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $user->givePermissionTo('reservations.view');
        $group = ReservationGroup::factory()->create([
            'company_id' => $user->company_id,
            'status' => 'confirmed',
            'check_in' => today()->toDateString(),
        ]);
        Reservation::factory()->create([
            'company_id' => $user->company_id,
            'reservation_group_id' => $group->id,
        ]);

        $this->actingAs($user)->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertDontSee('Acciones de reserva')
            ->assertDontSee('Modificar reserva')
            ->assertDontSee('Cancelar reserva');

        $user->givePermissionTo('occupancy.manage');
        $this->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSeeInOrder(['Saldo reserva', 'Acciones de reserva', 'Modificar reserva', 'Huesped titular'])
            ->assertSee(route('admin.reservation-groups.check-in', $group), false)
            ->assertDontSee('Cancelar reserva')
            ->assertDontSee('No se presentó')
            ->assertDontSee('Accesos');

        $user->givePermissionTo('reservations.manage');
        $this->get(route('admin.reservation-groups.show', $group))
            ->assertOk()
            ->assertSeeInOrder(['Saldo reserva', 'Acciones de reserva', 'Cancelar reserva', 'Huesped titular'])
            ->assertSee(route('admin.reservation-groups.no-show', $group), false)
            ->assertSee('data-reservation-reason-form', false)
            ->assertDontSee('Motivo opcional de cancelacion')
            ->assertDontSee('Confirmar reserva')
            ->assertDontSee('Accesos');
    }

    public function test_individual_reservation_keeps_its_actions_in_page_and_modal(): void
    {
        foreach (['reservations.view', 'reservations.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['reservations.view', 'reservations.manage']);
        $reservation = Reservation::factory()->create([
            'company_id' => $company->id,
            'guest_name' => 'Ana Vista Reserva',
            'status' => 'pending_payment',
            'balance_amount' => 100,
        ]);

        foreach ([[], ['X-Requested-With' => 'XMLHttpRequest']] as $headers) {
            $response = $this->actingAs($user)->get(route('admin.reservations.show', $reservation), $headers)
                ->assertOk()
                ->assertSee('check-in-read-header')
                ->assertSee('Ana Vista Reserva')
                ->assertSee('Saldo reserva')
                ->assertSee('is-pending')
                ->assertSee('Resumen de cuenta')
                ->assertSee('Agregar cargo')
                ->assertSee('Aprobar pago y confirmar')
                ->assertSee('Cancelar reserva')
                ->assertSee(route('admin.reservations.approve', $reservation), false)
                ->assertSee(route('admin.reservations.cancel', $reservation), false);
            if ($headers !== []) {
                $response->assertDontSee('<html', false);
            }
        }
    }

    public function test_cancelled_reservation_keeps_its_status_without_management_actions(): void
    {
        Permission::findOrCreate('reservations.view');
        Permission::findOrCreate('reservations.manage');
        $user = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $user->givePermissionTo(['reservations.view', 'reservations.manage']);
        $reservation = Reservation::factory()->create(['company_id' => $user->company_id, 'status' => 'cancelled']);

        $this->actingAs($user)->get(route('admin.reservations.show', $reservation), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('Cancelada')
            ->assertDontSee('Agregar cargo')
            ->assertDontSee('Aprobar pago y confirmar')
            ->assertDontSee('Cancelar reserva');
    }
}
