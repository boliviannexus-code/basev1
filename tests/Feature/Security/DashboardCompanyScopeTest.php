<?php

namespace Tests\Feature\Security;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\PrivateSpaceType;
use App\Models\ReservationGroup;
use App\Models\RoomBedUnit;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardCompanyScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_dashboard_is_scoped_to_their_company_occupancy(): void
    {
        Permission::findOrCreate('dashboard.view');

        $company = Company::factory()->create([
            'name' => 'Empresa Local',
            'logo_path' => 'companies/local-logo.png',
        ]);
        $otherCompany = Company::factory()->create(['name' => 'Empresa Ajena']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('dashboard.view');

        $privateSpace = $this->privateSpace($company, 'Casa Local');
        $sharedSpace = $this->sharedSpace($company, 'Hostal Local');
        $room = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $sharedSpace->id,
            'name' => 'Habitacion Azul',
            'status' => 'active',
        ]);

        $this->stay($company, $privateSpace, people: 2, breakfast: true, checkIn: now()->subDay(), checkOut: now()->addDay());
        $this->stay($company, $sharedSpace, $room, people: 1, breakfast: true, checkIn: now()->subDay(), checkOut: now()->addDay());
        $this->stay($company, $privateSpace, people: 1, checkIn: now(), checkOut: now()->addDays(2));
        $this->stay($company, $privateSpace, people: 1, checkIn: now()->subDay(), checkOut: now());

        $otherSpace = $this->privateSpace($otherCompany, 'Casa Ajena');
        $this->stay($otherCompany, $otherSpace, people: 9, breakfast: true, checkIn: now()->subDay(), checkOut: now()->addDay());

        ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'guest_name' => 'Reserva Local',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'guests' => 3,
        ]);
        ReservationGroup::factory()->create([
            'company_id' => $otherCompany->id,
            'guest_name' => 'Reserva Ajena',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => 'confirmed',
            'guests' => 8,
        ]);
        ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-DASH-0002',
            'guest_name' => 'Pendiente Hoy',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'pending_payment',
        ]);
        ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'code' => 'RSG-DASH-0003',
            'guest_name' => 'Pendiente Futuro',
            'check_in' => now()->addDay()->toDateString(),
            'check_out' => now()->addDays(2)->toDateString(),
            'status' => 'pending_payment',
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Empresa Local')
            ->assertSee('companies/local-logo.png')
            ->assertSee('Contexto de empresa')
            ->assertSee('Ocupacion')
            ->assertSee('Habitaciones ocupadas')
            ->assertSee('Disponibles esta noche')
            ->assertSee('Check-ins hoy')
            ->assertSee('Check-outs hoy')
            ->assertSee('Desayunos hoy')
            ->assertSee('Resumen por espacio')
            ->assertSee('Alertas operativas')
            ->assertSee('Habitaciones para check-out')
            ->assertSee('Habitaciones con saldo pendiente')
            ->assertSee('Reservas para hoy')
            ->assertSee('1 pendientes de pago o revision')
            ->assertDontSee('Informacion de empresa')
            ->assertDontSee('Reservas por estado')
            ->assertSee('Casa Local')
            ->assertSee('Hostal Local')
            ->assertSee('Reserva Local')
            ->assertDontSee('Empresa Ajena')
            ->assertDontSee('Casa Ajena')
            ->assertDontSee('Reserva Ajena');

        $occupancy = $response->viewData('occupancy');

        $this->assertSame(100, $occupancy['occupancy_rate']);
        $this->assertSame(2, $occupancy['occupied_units']);
        $this->assertSame(2, $occupancy['total_units']);
        $this->assertSame(0, $occupancy['available_units']);
        $this->assertSame(1, $occupancy['check_ins_today']);
        $this->assertSame(1, $occupancy['check_outs_today']);
        $this->assertCount(2, $occupancy['by_space']);

        $breakfast = $response->viewData('breakfast');

        $this->assertSame(3, $breakfast['total_people']);
        $this->assertSame(2, $breakfast['by_space']->firstWhere('space.id', $privateSpace->id)['people']);
        $this->assertSame(1, $breakfast['by_space']->firstWhere('space.id', $sharedSpace->id)['people']);

        $alerts = $response->viewData('alerts');

        $this->assertSame(1, $alerts['pending_reservations']);
        $this->assertCount(1, $alerts['check_out_rooms']);
        $this->assertCount(0, $alerts['pending_balance_rooms']);
        $this->assertCount(2, $alerts['reservations_today']);
    }

    public function test_global_super_admin_dashboard_sees_company_summary_without_financial_details(): void
    {
        Permission::findOrCreate('dashboard.view');
        Role::findOrCreate('super_admin')->givePermissionTo('dashboard.view');

        Company::factory()->create(['name' => 'Empresa Uno']);
        Company::factory()->create(['name' => 'Empresa Dos']);
        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole('super_admin');

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Todas las empresas')
            ->assertSee('Contexto global')
            ->assertSee('Empresa Uno')
            ->assertSee('Empresa Dos')
            ->assertSee('Sin datos financieros')
            ->assertDontSee('Total Bs');
    }

    public function test_super_admin_with_company_dashboard_is_scoped_to_assigned_company(): void
    {
        Permission::findOrCreate('dashboard.view');
        Role::findOrCreate('super_admin')->givePermissionTo('dashboard.view');

        $company = Company::factory()->create(['name' => 'Empresa Asignada']);
        $otherCompany = Company::factory()->create(['name' => 'Empresa Global No Visible']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->assignRole('super_admin');

        ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'guest_name' => 'Reserva Asignada',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'confirmed',
        ]);
        ReservationGroup::factory()->create([
            'company_id' => $otherCompany->id,
            'guest_name' => 'Reserva Oculta',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDay()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Empresa Asignada')
            ->assertSee('Contexto de empresa')
            ->assertSee('Reserva Asignada')
            ->assertDontSee('Empresa Global No Visible')
            ->assertDontSee('Reserva Oculta');
    }

    public function test_historical_date_includes_completed_stays_and_keeps_company_scope(): void
    {
        Permission::findOrCreate('dashboard.view');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('dashboard.view');
        $date = now()->subDays(10)->startOfDay();
        $space = $this->privateSpace($company, 'Casa historica');
        $stay = $this->stay($company, $space, people: 3, breakfast: true, checkIn: $date->copy()->subDay(), checkOut: $date->copy()->addDay());
        $stay->update(['status' => 'checked_out']);
        $otherCompany = Company::factory()->create();
        $this->stay($otherCompany, $this->privateSpace($otherCompany, 'Casa ajena'), people: 9, checkIn: $date->copy()->subDay(), checkOut: $date->copy()->addDay());

        $response = $this->actingAs($user)->get(route('dashboard', ['date' => $date->toDateString()]))
            ->assertOk()
            ->assertSee('Volver a hoy')
            ->assertSee('Check-ins en la fecha')
            ->assertSee('Proximas reservas (actuales)')
            ->assertDontSee('Casa ajena');

        $this->assertTrue($response->viewData('isHistorical'));
        $this->assertSame(3, $response->viewData('occupancy')['total_guests']);
        $this->assertSame(1, $response->viewData('occupancy')['occupied_units']);
        $this->assertSame(3, $response->viewData('breakfast')['total_people']);

        $departure = $this->get(route('dashboard', ['date' => $date->copy()->addDay()->toDateString()]))->assertOk();
        $this->assertSame(0, $departure->viewData('occupancy')['occupied_units']);
        $this->assertSame(1, $departure->viewData('occupancy')['check_outs_today']);
        $this->assertSame(3, $departure->viewData('breakfast')['total_people']);
    }

    public function test_room_occupancy_counts_each_room_once_for_all_sale_modes(): void
    {
        Permission::findOrCreate('dashboard.view');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('dashboard.view');
        $space = $this->sharedSpace($company, 'Backpackers');
        $date = now()->subDays(2)->startOfDay();

        foreach (['flexible', 'bed_unit', 'full_room'] as $mode) {
            $room = SpaceRoom::factory()->create([
                'company_id' => $company->id, 'space_id' => $space->id,
                'status' => 'active', 'sale_mode' => $mode,
            ]);
            foreach (range(1, 3) as $number) {
                $bed = RoomBedUnit::factory()->create([
                    'company_id' => $company->id, 'space_room_id' => $room->id,
                ]);
                if ($mode !== 'full_room') {
                    $this->stay($company, $space, $room, checkIn: $date->copy()->subDay(), checkOut: $date->copy()->addDay())
                        ->update(['room_bed_unit_id' => $bed->id, 'status' => 'checked_out']);
                }
            }
            if ($mode === 'flexible') {
                $this->stay($company, $space, $room, checkIn: $date->copy()->subDay(), checkOut: $date->copy()->addDay());
            }
        }

        $response = $this->actingAs($user)->get(route('dashboard', ['date' => $date->toDateString()]))->assertOk();
        $row = $response->viewData('occupancy')['by_space']->firstWhere('space.id', $space->id);
        $this->assertSame(3, $row['total_units']);
        $this->assertSame(2, $row['occupied_units']);
        $this->assertSame(1, $row['available_units']);
        $this->assertSame(67, $row['occupancy_rate']);
        $this->assertSame(67, $response->viewData('occupancy')['occupancy_rate']);
    }

    public function test_dashboard_rejects_invalid_and_future_dates(): void
    {
        Permission::findOrCreate('dashboard.view');
        $user = User::factory()->create();
        $user->givePermissionTo('dashboard.view');

        foreach (['invalid', '2026-02-30', now()->addDay()->toDateString()] as $date) {
            $this->actingAs($user)->get(route('dashboard', ['date' => $date]))
                ->assertSessionHasErrors('date');
        }
    }

    private function privateSpace(Company $company, string $name): Space
    {
        return Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::query()->firstOrCreate(
                ['slug' => 'privado'],
                ['name' => 'Privado', 'is_active' => true],
            )->id,
            'private_space_type_id' => PrivateSpaceType::query()->firstOrCreate(
                ['slug' => 'casa'],
                ['name' => 'Casa', 'is_active' => true],
            )->id,
            'title' => $name,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function sharedSpace(Company $company, string $name): Space
    {
        return Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::query()->firstOrCreate(
                ['slug' => 'compartido'],
                ['name' => 'Compartido', 'is_active' => true],
            )->id,
            'shared_space_type_id' => SharedSpaceType::query()->firstOrCreate(
                ['slug' => 'hostal'],
                ['name' => 'Hostal', 'is_active' => true],
            )->id,
            'private_space_type_id' => null,
            'title' => $name,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function stay(
        Company $company,
        Space $space,
        ?SpaceRoom $room = null,
        int $people = 1,
        bool $breakfast = false,
        mixed $checkIn = null,
        mixed $checkOut = null,
    ): Stay {
        $holder = Guest::factory()->create(['company_id' => $company->id]);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'main_guest_id' => $holder->id,
            'check_in_date' => ($checkIn ?: now())->toDateString(),
            'check_out_date' => ($checkOut ?: now()->addDay())->toDateString(),
            'total_people' => $people,
        ]);

        return Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $holder->id,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'people_count' => $people,
            'check_in_date' => ($checkIn ?: now())->toDateString(),
            'check_out_date' => ($checkOut ?: now()->addDay())->toDateString(),
            'breakfast_included' => $breakfast,
            'status' => 'occupied',
        ]);
    }
}
