<?php

namespace Tests\Feature\SpaceCash;

use App\Models\Company;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OpenSpaceCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_open_space_cash_register_without_inventory_pos(): void
    {
        $user = $this->userWithSpaceCashAccess();

        $this
            ->actingAs($user)
            ->post(route('space-cash.open'), [
                'opening_amount' => 150.25,
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_registers', [
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_amount' => '150.25',
            'status' => 'open',
        ]);
    }

    public function test_user_cannot_open_two_own_space_cash_registers(): void
    {
        $user = $this->userWithSpaceCashAccess();
        SpaceCashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('space-cash.open'), [
                'opening_amount' => 10,
            ])
            ->assertSessionHasErrors('opening_amount');
    }

    public function test_two_users_can_open_independent_space_cash_registers_in_same_company(): void
    {
        $company = Company::factory()->create();
        $firstUser = $this->userWithSpaceCashAccess($company->id);
        $secondUser = $this->userWithSpaceCashAccess($company->id);

        $this
            ->actingAs($firstUser)
            ->post(route('space-cash.open'), [
                'opening_amount' => 20,
            ])
            ->assertRedirect(route('space-cash.index'));

        $this
            ->actingAs($secondUser)
            ->post(route('space-cash.open'), [
                'opening_amount' => 30,
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_registers', [
            'company_id' => $company->id,
            'user_id' => $firstUser->id,
            'opening_amount' => '20.00',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('space_cash_registers', [
            'company_id' => $company->id,
            'user_id' => $secondUser->id,
            'opening_amount' => '30.00',
            'status' => 'open',
        ]);
    }

    public function test_user_can_register_space_cash_expense_with_extra_charge_category(): void
    {
        $user = $this->userWithSpaceCashAccess();
        $category = $this->expenseCategory($user->company_id);
        $cashRegister = SpaceCashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('space-cash.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Lavado de sabanas',
                'amount' => 25,
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_expenses', [
            'company_id' => $user->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $user->id,
            'extra_charge_category_id' => $category->id,
            'responsible_name' => 'Maria Caja',
            'detail' => 'Lavado de sabanas',
            'amount' => '25.00',
        ]);
    }

    public function test_user_can_register_space_cash_direct_income_with_extra_charge_category(): void
    {
        $user = $this->userWithSpaceCashAccess();
        $category = $this->expenseCategory($user->company_id);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);
        $cashRegister = SpaceCashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_amount' => 50,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('space-cash.incomes.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Lavanderia sin huesped',
                'reference' => 'REC-22',
                'amount' => 30,
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_incomes', [
            'company_id' => $user->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $user->id,
            'extra_charge_category_id' => $category->id,
            'payment_method_id' => $paymentMethod->id,
            'responsible_name' => 'Maria Caja',
            'detail' => 'Lavanderia sin huesped',
            'reference' => 'REC-22',
            'amount' => '30.00',
        ]);
    }

    private function userWithSpaceCashAccess(?int $companyId = null): User
    {
        Permission::findOrCreate('space-cash.access');

        $companyId ??= Company::factory()->create()->id;
        $user = User::factory()->create(['company_id' => $companyId]);
        $user->givePermissionTo('space-cash.access');

        return $user;
    }

    private function expenseCategory(int $companyId): ExtraChargeCategory
    {
        return ExtraChargeCategory::query()->create([
            'company_id' => $companyId,
            'name' => 'Lavanderia',
            'default_unit_price' => 0,
            'currency' => 'BOB',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }
}
