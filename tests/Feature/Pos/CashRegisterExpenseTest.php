<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashRegisterExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_cash_expense_when_cash_is_available(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->expenseCategory($user->company_id);
        $paymentMethod = $this->paymentMethod($user->company_id);
        [$pointOfSale, $branch] = $this->assignedPointOfSale($user);
        $cashRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'quantity' => 1,
                'amount' => 25.50,
                'transaction_pin' => '1234',
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_id' => $cashRegister->id,
            'company_id' => $user->company_id,
            'point_of_sale_id' => null,
            'user_id' => $user->id,
            'extra_charge_category_id' => $category->id,
            'responsible_name' => 'Maria Caja',
            'detail' => 'Compra de bolsas',
            'amount' => '25.50',
        ]);
    }

    public function test_cash_expense_cannot_exceed_available_cash_in_open_register(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->expenseCategory($user->company_id);
        $paymentMethod = $this->paymentMethod($user->company_id);
        [$pointOfSale, $branch] = $this->assignedPointOfSale($user);
        CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => 20,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'quantity' => 1,
                'amount' => 21,
                'transaction_pin' => '1234',
            ])
            ->assertSessionHasErrors(['amount'], null, 'cashExpense');

        $this->assertDatabaseCount('cash_register_expenses', 0);
    }

    public function test_cash_expense_available_cash_includes_cash_sales_from_same_register(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->expenseCategory($user->company_id);
        $paymentMethod = $this->paymentMethod($user->company_id);
        [$pointOfSale, $branch, $warehouse] = $this->assignedPointOfSale($user);
        $cashRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => 0,
            'status' => 'open',
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => $pointOfSale->id,
            'receipt_number' => 'POS-000001',
            'sequence_number' => 1,
            'sale_date' => now(),
            'subtotal' => 30,
            'discount' => 0,
            'tax' => 0,
            'total' => 30,
            'status' => 'completed',
        ]);
        $sale->payments()->create([
            'payment_method_name' => 'Efectivo',
            'amount' => 30,
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Taxi de reparto',
                'quantity' => 1,
                'amount' => 20,
                'transaction_pin' => '1234',
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_id' => $cashRegister->id,
            'extra_charge_category_id' => $category->id,
            'amount' => '20.00',
        ]);
    }

    public function test_cash_expense_requires_open_cash_register(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->expenseCategory($user->company_id);
        $paymentMethod = $this->paymentMethod($user->company_id);

        $this
            ->actingAs($user)
            ->post(route('pos.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'quantity' => 1,
                'amount' => 5,
                'transaction_pin' => '1234',
            ])
            ->assertSessionHasErrors(['transaction_pin'], null, 'cashExpense');
    }

    public function test_cash_expense_uses_pin_owner_cash_register_even_from_another_session(): void
    {
        $company = Company::factory()->create();
        $sessionUser = $this->userWithPosAccess($company->id, '1234');
        $cashOwner = $this->userWithPosAccess($company->id, '9876');
        $category = $this->expenseCategory($company->id);
        $paymentMethod = $this->paymentMethod($company->id);
        $cashRegister = CashRegister::factory()->create([
            'company_id' => $company->id,
            'user_id' => $cashOwner->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        $this
            ->actingAs($sessionUser)
            ->post(route('pos.expenses.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'responsible_name' => 'Recepcion',
                'detail' => 'Egreso con codigo de caja',
                'quantity' => 1,
                'amount' => 25.50,
                'transaction_pin' => '9876',
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_id' => $cashRegister->id,
            'company_id' => $company->id,
            'user_id' => $cashOwner->id,
            'detail' => 'Egreso con codigo de caja',
            'amount' => '25.50',
        ]);
    }

    private function assignedPointOfSale(User $user): array
    {
        $branch = Branch::factory()->create(['company_id' => $user->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create(['company_id' => $user->company_id]);
        $pointOfSale = PointOfSale::factory()->forWarehouse($warehouse->id)->create();
        $pointOfSale->users()->sync([$user->id]);

        return [$pointOfSale, $branch, $warehouse];
    }

    private function userWithPosAccess(?int $companyId = null, string $transactionPin = '1234'): User
    {
        Permission::findOrCreate('pos.access');

        $companyId ??= Company::factory()->create()->id;
        $user = User::factory()->create([
            'company_id' => $companyId,
            'transaction_pin' => Hash::make($transactionPin),
        ]);
        $user->givePermissionTo('pos.access');

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
