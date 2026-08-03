<?php

namespace Tests\Feature\SpaceCash;

use App\Models\Company;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_user_can_view_space_cash_when_only_another_user_has_open_register(): void
    {
        $company = Company::factory()->create();
        $sessionUser = $this->userWithSpaceCashAccess($company->id);
        $cashOwner = $this->userWithSpaceCashAccess($company->id);

        SpaceCashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $cashOwner->id,
            'status' => 'open',
        ]);

        $this
            ->actingAs($sessionUser)
            ->get(route('space-cash.index'))
            ->assertOk()
            ->assertSee('No tienes una caja de espacios abierta en tu sesion')
            ->assertSee('Caja con codigo de otro usuario');
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
                'payment_method_id' => $this->paymentMethod($user->company_id)->id,
                'quantity' => 1,
                'amount' => 25,
                'transaction_pin' => '1234',
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
                'quantity' => 1,
                'amount' => 30,
                'transaction_pin' => '1234',
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

    public function test_direct_income_uses_pin_owner_cash_register_even_from_another_session(): void
    {
        $company = Company::factory()->create();
        $sessionUser = $this->userWithSpaceCashAccess($company->id, '1234');
        $cashOwner = $this->userWithSpaceCashAccess($company->id, '9876');
        $category = $this->expenseCategory($company->id);
        $paymentMethod = $this->paymentMethod($company->id);
        $cashRegister = SpaceCashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $cashOwner->id,
            'opening_amount' => 10,
            'status' => 'open',
        ]);

        $this
            ->actingAs($sessionUser)
            ->post(route('space-cash.incomes.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Recepcion',
                'detail' => 'Ingreso con codigo de caja',
                'quantity' => 1,
                'reference' => 'PIN-OWNER',
                'amount' => 30,
                'transaction_pin' => '9876',
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_incomes', [
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $cashOwner->id,
            'reference' => 'PIN-OWNER',
            'amount' => '30.00',
        ]);
    }

    public function test_expense_uses_pin_owner_cash_register_even_from_another_session(): void
    {
        $company = Company::factory()->create();
        $sessionUser = $this->userWithSpaceCashAccess($company->id, '1234');
        $cashOwner = $this->userWithSpaceCashAccess($company->id, '9876');
        $category = $this->expenseCategory($company->id);
        $paymentMethod = $this->paymentMethod($company->id);
        $cashRegister = SpaceCashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $cashOwner->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        $this
            ->actingAs($sessionUser)
            ->post(route('space-cash.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Recepcion',
                'detail' => 'Egreso con codigo de caja',
                'quantity' => 1,
                'amount' => 30,
                'transaction_pin' => '9876',
            ])
            ->assertRedirect(route('space-cash.index'));

        $this->assertDatabaseHas('space_cash_expenses', [
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $cashOwner->id,
            'detail' => 'Egreso con codigo de caja',
            'amount' => '30.00',
        ]);
    }

    private function userWithSpaceCashAccess(?int $companyId = null, string $transactionPin = '1234'): User
    {
        Permission::findOrCreate('space-cash.access');

        $companyId ??= Company::factory()->create()->id;
        $user = User::factory()->create([
            'company_id' => $companyId,
            'transaction_pin' => Hash::make($transactionPin),
        ]);
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

    private function paymentMethod(int $companyId): PaymentMethod
    {
        return PaymentMethod::query()->create([
            'company_id' => $companyId,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);
    }
}
