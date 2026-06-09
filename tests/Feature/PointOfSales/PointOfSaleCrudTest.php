<?php

namespace Tests\Feature\PointOfSales;

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

class PointOfSaleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_point_of_sale_can_be_created_for_company_without_warehouse(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.create']);

        $this
            ->actingAs($user)
            ->post(route('point-of-sales.store'), [
                'branch_id' => null,
                'warehouse_id' => null,
                'name' => 'Caja mostrador',
                'is_active' => '1',
            ])
            ->assertRedirect(route('point-of-sales.index'));

        $this->assertDatabaseHas('point_of_sales', [
            'company_id' => $user->company_id,
            'branch_id' => null,
            'warehouse_id' => null,
            'name' => 'Caja mostrador',
            'code' => 'PV-000001',
        ]);
    }

    public function test_user_can_create_point_of_sale_linked_to_warehouse(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.create']);
        $branch = Branch::factory()->create(['company_id' => $user->company_id, 'code' => 'SUC-CENTRAL']);
        $cashier = User::factory()->create(['company_id' => $branch->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create(['code' => 'ALM-PRINCIPAL']);

        $this
            ->actingAs($user)
            ->post(route('point-of-sales.store'), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS principal',
                'receipt_prefix' => 'MOSTRADOR',
                'receipt_next_number' => 100,
                'receipt_digits' => 5,
                'users' => [$cashier->id],
                'is_active' => '1',
            ])
            ->assertRedirect(route('point-of-sales.index'));

        $this->assertDatabaseHas('point_of_sales', [
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'POS principal',
            'code' => 'PV-000001',
            'sequence_number' => 1,
            'receipt_prefix' => 'MOSTRADOR',
            'receipt_next_number' => 100,
            'receipt_digits' => 5,
        ]);
        $this->assertDatabaseHas('point_of_sale_user', [
            'point_of_sale_id' => PointOfSale::query()->where('warehouse_id', $warehouse->id)->value('id'),
            'user_id' => $cashier->id,
        ]);
    }

    public function test_point_of_sale_rejects_warehouse_already_linked_to_another_point_of_sale(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.create']);
        $branch = Branch::factory()->create(['company_id' => $user->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create();

        PointOfSale::factory()->for($branch)->create([
            'warehouse_id' => $warehouse->id,
            'code' => 'PV-000001',
            'sequence_number' => 1,
        ]);

        $this
            ->actingAs($user)
            ->post(route('point-of-sales.store'), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS duplicado',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('warehouse_id');
    }

    public function test_point_of_sale_rejects_warehouse_from_another_branch(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.create']);
        $branch = Branch::factory()->create(['company_id' => $user->company_id]);
        $otherBranch = Branch::factory()->create(['company_id' => $user->company_id]);
        $otherWarehouse = Warehouse::factory()->for($otherBranch)->create();

        $this
            ->actingAs($user)
            ->post(route('point-of-sales.store'), [
                'branch_id' => $branch->id,
                'warehouse_id' => $otherWarehouse->id,
                'name' => 'POS inconsistente',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('warehouse_id');
    }

    public function test_user_can_update_point_of_sale_warehouse_link(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.update']);
        $branch = Branch::factory()->create(['company_id' => $user->company_id, 'code' => 'SUC-NORTE']);
        $cashier = User::factory()->create(['company_id' => $branch->company_id]);
        $oldWarehouse = Warehouse::factory()->for($branch)->create(['code' => 'ALM-OLD']);
        $warehouse = Warehouse::factory()->for($branch)->create(['code' => 'ALM-NUEVO']);
        $pointOfSale = PointOfSale::factory()->for($branch)->create([
            'warehouse_id' => $oldWarehouse->id,
            'code' => 'PV-000001',
            'sequence_number' => 1,
        ]);

        $this
            ->actingAs($user)
            ->put(route('point-of-sales.update', $pointOfSale), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS actualizado',
                'receipt_prefix' => 'CAJA',
                'receipt_next_number' => 12,
                'receipt_digits' => 4,
                'users' => [$cashier->id],
                'is_active' => '1',
            ])
            ->assertRedirect(route('point-of-sales.index'));

        $this->assertDatabaseHas('point_of_sales', [
            'id' => $pointOfSale->id,
            'warehouse_id' => $warehouse->id,
            'name' => 'POS actualizado',
            'code' => 'PV-000001',
            'receipt_prefix' => 'CAJA',
            'receipt_next_number' => 12,
            'receipt_digits' => 4,
        ]);
        $this->assertDatabaseHas('point_of_sale_user', [
            'point_of_sale_id' => $pointOfSale->id,
            'user_id' => $cashier->id,
        ]);
    }

    public function test_assigning_user_to_point_of_sale_removes_legacy_assignments_from_other_companies(): void
    {
        $actor = $this->userWithPermissions(['point-of-sales.update']);
        $otherCompany = Company::factory()->create();
        $cashier = User::factory()->create(['company_id' => $actor->company_id]);

        $branch = Branch::factory()->create(['company_id' => $actor->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create(['company_id' => $actor->company_id]);
        $pointOfSale = PointOfSale::factory()->forWarehouse($warehouse->id)->create();

        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $otherWarehouse = Warehouse::factory()->for($otherBranch)->create(['company_id' => $otherCompany->id]);
        $legacyPointOfSale = PointOfSale::factory()->forWarehouse($otherWarehouse->id)->create();
        $legacyPointOfSale->users()->sync([$cashier->id]);

        $this
            ->actingAs($actor)
            ->put(route('point-of-sales.update', $pointOfSale), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS sin cruces',
                'receipt_next_number' => 1,
                'receipt_digits' => 6,
                'users' => [$cashier->id],
                'is_active' => '1',
            ])
            ->assertRedirect(route('point-of-sales.index'));

        $this->assertDatabaseHas('point_of_sale_user', [
            'point_of_sale_id' => $pointOfSale->id,
            'user_id' => $cashier->id,
        ]);
        $this->assertDatabaseMissing('point_of_sale_user', [
            'point_of_sale_id' => $legacyPointOfSale->id,
            'user_id' => $cashier->id,
        ]);
    }

    public function test_user_cannot_move_receipt_sequence_before_existing_sales(): void
    {
        $user = $this->userWithPermissions(['point-of-sales.update']);
        $branch = Branch::factory()->create(['company_id' => $user->company_id]);
        $warehouse = Warehouse::factory()->for($branch)->create();
        $pointOfSale = PointOfSale::factory()->forWarehouse($warehouse->id)->create([
            'receipt_prefix' => 'CAJA',
            'receipt_next_number' => 11,
        ]);
        $cashRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $pointOfSale->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);
        Sale::query()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => $pointOfSale->id,
            'receipt_number' => 'CAJA-000010',
            'sequence_number' => 10,
            'sale_date' => now(),
            'subtotal' => 1,
            'discount' => 0,
            'tax' => 0,
            'total' => 1,
            'status' => 'completed',
        ]);

        $this
            ->actingAs($user)
            ->put(route('point-of-sales.update', $pointOfSale), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS actualizado',
                'receipt_prefix' => 'CAJA',
                'receipt_next_number' => 10,
                'receipt_digits' => 6,
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('receipt_next_number');

        $this->assertSame(11, $pointOfSale->refresh()->receipt_next_number);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $user = User::factory()->create(['company_id' => Company::factory()]);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user->givePermissionTo($permissions);

        return $user;
    }
}
