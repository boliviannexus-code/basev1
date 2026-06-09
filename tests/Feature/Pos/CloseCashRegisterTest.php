<?php

namespace Tests\Feature\Pos;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\CashRegisterExpense;
use App\Models\Company;
use App\Models\PointOfSale;
use App\Models\Sale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CloseCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_close_open_cash_register(): void
    {
        $user = $this->userWithPosAccess();
        [$pointOfSale, $branch] = $this->assignedPointOfSale($user);
        $cashRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => 50,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.close'), [
                'closing_amount' => 75.50,
            ])
            ->assertRedirect(route('pos.index'));

        $cashRegister->refresh();

        $this->assertSame('closed', $cashRegister->status);
        $this->assertSame('75.50', $cashRegister->closing_amount);
        $this->assertNotNull($cashRegister->closed_at);
    }

    public function test_user_cannot_close_without_open_cash_register(): void
    {
        $user = $this->userWithPosAccess();

        $this
            ->actingAs($user)
            ->post(route('pos.close'), [
                'closing_amount' => 10,
            ])
            ->assertSessionHasErrors(['closing_amount'], null, 'cashClose');
    }

    public function test_pos_screen_shows_cash_close_summary_before_confirmation(): void
    {
        $user = $this->userWithPosAccess();
        [$pointOfSale, $branch, $warehouse] = $this->assignedPointOfSale($user);
        $cashRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'opening_amount' => 20,
            'status' => 'open',
        ]);
        $sale = Sale::query()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => $pointOfSale->id,
            'receipt_number' => 'POS-000010',
            'sequence_number' => 10,
            'sale_date' => now(),
            'subtotal' => 40,
            'discount' => 0,
            'tax' => 0,
            'total' => 40,
            'status' => 'completed',
        ]);
        $sale->payments()->create([
            'payment_method_name' => 'Efectivo',
            'amount' => 30,
        ]);
        $sale->payments()->create([
            'payment_method_name' => 'QR',
            'amount' => 10,
        ]);
        CashRegisterExpense::query()->create([
            'company_id' => $user->company_id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => $pointOfSale->id,
            'user_id' => $user->id,
            'responsible_name' => 'Maria Caja',
            'detail' => 'Movilidad',
            'amount' => 5,
            'spent_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('pos.index'))
            ->assertOk()
            ->assertSee('Cierre de caja')
            ->assertSee('POS-000010')
            ->assertSee('Movilidad')
            ->assertSee('45.00');
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
