<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashRegisterExpenseTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_cash_expense_when_cash_is_available(): void
    {
        $user = $this->userWithPosAccess();
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
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'amount' => 25.50,
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_id' => $cashRegister->id,
            'company_id' => $user->company_id,
            'point_of_sale_id' => null,
            'user_id' => $user->id,
            'responsible_name' => 'Maria Caja',
            'detail' => 'Compra de bolsas',
            'amount' => '25.50',
        ]);
    }

    public function test_cash_expense_cannot_exceed_available_cash_in_open_register(): void
    {
        $user = $this->userWithPosAccess();
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
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'amount' => 21,
            ])
            ->assertSessionHasErrors(['amount'], null, 'cashExpense');

        $this->assertDatabaseCount('cash_register_expenses', 0);
    }

    public function test_cash_expense_available_cash_includes_cash_sales_from_same_register(): void
    {
        $user = $this->userWithPosAccess();
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
                'responsible_name' => 'Maria Caja',
                'detail' => 'Taxi de reparto',
                'amount' => 20,
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('cash_register_expenses', [
            'cash_register_id' => $cashRegister->id,
            'amount' => '20.00',
        ]);
    }

    public function test_cash_expense_requires_open_cash_register(): void
    {
        $user = $this->userWithPosAccess();

        $this
            ->actingAs($user)
            ->post(route('pos.expenses.store'), [
                'responsible_name' => 'Maria Caja',
                'detail' => 'Compra de bolsas',
                'amount' => 5,
            ])
            ->assertSessionHasErrors(['amount'], null, 'cashExpense');
    }

    private function assignedPointOfSale(User $user): array
    {
        $branch = Branch::factory()->create(['company_id' => $user->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create(['company_id' => $user->company_id]);
        $pointOfSale = PointOfSale::factory()->forWarehouse($warehouse->id)->create();
        $pointOfSale->users()->sync([$user->id]);

        return [$pointOfSale, $branch, $warehouse];
    }

    private function userWithPosAccess(): User
    {
        Permission::findOrCreate('pos.access');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('pos.access');

        return $user;
    }
}
