<?php

namespace Tests\Feature\Pos;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OpenCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_cash_register_without_point_of_sale(): void
    {
        $user = $this->userWithPosAccess();

        $this
            ->actingAs($user)
            ->post(route('pos.open'), [
                'opening_amount' => 150.25,
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $user->company_id,
            'point_of_sale_id' => null,
            'branch_id' => null,
            'user_id' => $user->id,
            'opening_amount' => '150.25',
            'status' => 'open',
        ]);
    }

    public function test_user_cannot_open_two_own_cash_registers(): void
    {
        $user = $this->userWithPosAccess();
        CashRegister::factory()->create([
            'company_id' => $user->company_id,
            'point_of_sale_id' => null,
            'branch_id' => null,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.open'), [
                'opening_amount' => 10,
            ])
            ->assertSessionHasErrors('opening_amount');
    }

    public function test_two_users_can_open_independent_cash_registers_in_same_company(): void
    {
        $company = Company::factory()->create();
        $firstUser = $this->userWithPosAccess($company->id);
        $secondUser = $this->userWithPosAccess($company->id);

        $this
            ->actingAs($firstUser)
            ->post(route('pos.open'), [
                'opening_amount' => 20,
            ])
            ->assertRedirect(route('pos.index'));

        $this
            ->actingAs($secondUser)
            ->post(route('pos.open'), [
                'opening_amount' => 30,
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $company->id,
            'user_id' => $firstUser->id,
            'opening_amount' => '20.00',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('cash_registers', [
            'company_id' => $company->id,
            'user_id' => $secondUser->id,
            'opening_amount' => '30.00',
            'status' => 'open',
        ]);
    }

    public function test_pos_screen_does_not_show_point_of_sale_selector(): void
    {
        $user = $this->userWithPosAccess();

        $this
            ->actingAs($user)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Abrir caja')
            ->assertDontSee('point_of_sale_id')
            ->assertDontSee('Seleccionar punto de venta');
    }

    private function userWithPosAccess(?int $companyId = null): User
    {
        Permission::findOrCreate('pos.access');

        $companyId ??= Company::factory()->create()->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo('pos.access');

        return $user;
    }
}
