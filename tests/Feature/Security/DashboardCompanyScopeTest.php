<?php

namespace Tests\Feature\Security;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\PrivateSpaceType;
use App\Models\ReservationGroup;
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

        $this
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Empresa Local')
            ->assertSee('companies/local-logo.png')
            ->assertSee('Contexto de empresa')
            ->assertSee('Habitaciones compartidas ocupadas')
            ->assertSee('Espacios privados ocupados')
            ->assertSee('Desayunos para hoy')
            ->assertSee('Casa Local')
            ->assertSee('Hostal Local')
            ->assertSee('Reserva Local')
            ->assertDontSee('Empresa Ajena')
            ->assertDontSee('Casa Ajena')
            ->assertDontSee('Reserva Ajena');
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
            ->assertDontSee('Total Bs')
            ->assertDontSee('Saldo');
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
