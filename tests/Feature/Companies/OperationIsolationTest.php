<?php

namespace Tests\Feature\Companies;

use App\Enums\InventoryMovementType;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Company;
use App\Models\InventoryMovement;
use App\Models\PointOfSale;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_only_sees_own_branches(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['branches.view']);
        Branch::factory()->for($company)->create(['name' => 'Sucursal Propia']);
        Branch::factory()->for($otherCompany)->create(['name' => 'Sucursal Ajena']);

        $this
            ->actingAs($user)
            ->get(route('branches.index'))
            ->assertOk()
            ->assertSee('Sucursal Propia')
            ->assertDontSee('Sucursal Ajena');
    }

    public function test_branch_created_by_company_user_is_assigned_to_user_company(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['branches.create']);

        $this
            ->actingAs($user)
            ->post(route('branches.store'), [
                'company_id' => $otherCompany->id,
                'name' => 'Sucursal Nueva',
                'code' => 'SUC-NUEVA',
                'is_active' => '1',
            ])
            ->assertRedirect(route('branches.index'));

        $this->assertDatabaseHas('branches', [
            'company_id' => $company->id,
            'code' => 'SUC-NUEVA',
        ]);
    }

    public function test_company_user_cannot_create_warehouse_for_other_company_branch(): void
    {
        [, $otherCompany, $user] = $this->companyUser(['warehouses.create']);
        $otherBranch = Branch::factory()->for($otherCompany)->create();

        $this
            ->actingAs($user)
            ->post(route('warehouses.store'), [
                'branch_id' => $otherBranch->id,
                'name' => 'Almacen ajeno',
                'code' => 'ALM-AJENO',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('branch_id');
    }

    public function test_company_user_cannot_assign_other_company_user_to_point_of_sale(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['point-of-sales.create']);
        $branch = Branch::factory()->for($company)->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['company_id' => $company->id]);
        $otherUser = User::factory()->for($otherCompany)->create(['is_active' => true]);

        $this
            ->actingAs($user)
            ->post(route('point-of-sales.store'), [
                'branch_id' => $branch->id,
                'warehouse_id' => $warehouse->id,
                'name' => 'POS principal',
                'users' => [$otherUser->id],
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('users.0');
    }

    public function test_company_user_cannot_view_other_company_point_of_sale(): void
    {
        [, $otherCompany, $user] = $this->companyUser(['point-of-sales.view']);
        $otherBranch = Branch::factory()->for($otherCompany)->create();
        $otherWarehouse = Warehouse::factory()->for($otherBranch)->create(['company_id' => $otherCompany->id]);
        $pointOfSale = PointOfSale::factory()->for($otherBranch)->create([
            'company_id' => $otherCompany->id,
            'warehouse_id' => $otherWarehouse->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('point-of-sales.show', $pointOfSale))
            ->assertForbidden();
    }

    public function test_company_user_only_sees_own_purchases_and_cannot_view_other_company_purchase(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['purchases.view']);
        $ownWarehouse = $this->warehouseForCompany($company, 'Almacen compras propio');
        $otherWarehouse = $this->warehouseForCompany($otherCompany, 'Almacen compras ajeno');
        $ownSupplier = Supplier::factory()->create(['company_id' => $company->id, 'name' => 'Proveedor Compra Propio']);
        $otherSupplier = Supplier::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Proveedor Compra Ajeno']);
        $ownPurchase = Purchase::factory()->create([
            'supplier_id' => $ownSupplier->id,
            'warehouse_id' => $ownWarehouse->id,
            'user_id' => $user->id,
            'reference' => 'COMPRA-PROPIA',
            'purchase_date' => now()->toDateString(),
        ]);
        $otherPurchase = Purchase::factory()->create([
            'supplier_id' => $otherSupplier->id,
            'warehouse_id' => $otherWarehouse->id,
            'reference' => 'COMPRA-AJENA',
            'purchase_date' => now()->toDateString(),
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.purchases'))
            ->assertOk()
            ->assertSee('COMPRA-PROPIA')
            ->assertDontSee('COMPRA-AJENA');

        $this
            ->actingAs($user)
            ->get(route('purchases.show', $ownPurchase))
            ->assertOk()
            ->assertSee('COMPRA-PROPIA');

        $this
            ->actingAs($user)
            ->get(route('purchases.show', $otherPurchase))
            ->assertForbidden();
    }

    public function test_company_user_only_sees_own_sales_and_cash_registers(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['sales.view']);
        [$ownPointOfSale, $ownBranch, $ownWarehouse] = $this->pointOfSaleForCompany($company, $user);
        [$otherPointOfSale, $otherBranch, $otherWarehouse] = $this->pointOfSaleForCompany($otherCompany);
        $ownRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $ownPointOfSale->id,
            'branch_id' => $ownBranch->id,
            'user_id' => $user->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);
        $otherRegister = CashRegister::factory()->create([
            'point_of_sale_id' => $otherPointOfSale->id,
            'branch_id' => $otherBranch->id,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $this->saleForRegister($ownRegister, $ownBranch, $ownWarehouse, $user, 'VENTA-PROPIA');
        $this->saleForRegister($otherRegister, $otherBranch, $otherWarehouse, User::factory()->for($otherCompany)->create(), 'VENTA-AJENA');

        $this
            ->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee(route('sales.cash-registers.show', $ownRegister))
            ->assertDontSee(route('sales.cash-registers.show', $otherRegister));

        $this
            ->actingAs($user)
            ->getJson(route('datatables.sales'))
            ->assertOk()
            ->assertSee('VENTA-PROPIA')
            ->assertDontSee('VENTA-AJENA');

        $this
            ->actingAs($user)
            ->get(route('sales.cash-registers.show', $otherRegister))
            ->assertForbidden();
    }

    public function test_company_user_only_sees_own_stock_rows(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['inventory.view']);
        $ownWarehouse = $this->warehouseForCompany($company, 'Almacen stock propio');
        $otherWarehouse = $this->warehouseForCompany($otherCompany, 'Almacen stock ajeno');
        $ownProduct = Product::factory()->create(['company_id' => $company->id, 'name' => 'Stock Producto Propio']);
        $otherProduct = Product::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Stock Producto Ajeno']);

        $this->inventoryMovement($ownProduct, $ownWarehouse, $user, 10);
        $this->inventoryMovement($otherProduct, $otherWarehouse, User::factory()->for($otherCompany)->create(), 20);

        $this
            ->actingAs($user)
            ->getJson(route('datatables.stock'))
            ->assertOk()
            ->assertSee('Stock Producto Propio')
            ->assertDontSee('Stock Producto Ajeno')
            ->assertDontSee('Almacen stock ajeno');
    }

    public function test_company_user_only_sees_own_kardex_and_cannot_view_other_company_detail(): void
    {
        [$company, $otherCompany, $user] = $this->companyUser(['inventory.view']);
        $ownWarehouse = $this->warehouseForCompany($company, 'Almacen kardex propio');
        $otherWarehouse = $this->warehouseForCompany($otherCompany, 'Almacen kardex ajeno');
        $ownProduct = Product::factory()->create(['company_id' => $company->id, 'name' => 'Kardex Producto Propio']);
        $otherProduct = Product::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Kardex Producto Ajeno']);

        $this->inventoryMovement($ownProduct, $ownWarehouse, $user, 10, 'Movimiento propio');
        $this->inventoryMovement($otherProduct, $otherWarehouse, User::factory()->for($otherCompany)->create(), 20, 'Movimiento ajeno');

        $this
            ->actingAs($user)
            ->getJson(route('datatables.kardex'))
            ->assertOk()
            ->assertSee('Kardex Producto Propio')
            ->assertDontSee('Kardex Producto Ajeno')
            ->assertDontSee('Almacen kardex ajeno');

        $this
            ->actingAs($user)
            ->get(route('inventory.kardex.show', [
                'product' => $ownProduct,
                'warehouse_id' => $ownWarehouse->id,
            ]))
            ->assertOk()
            ->assertSee('Movimiento propio');

        $this
            ->actingAs($user)
            ->get(route('inventory.kardex.show', [
                'product' => $otherProduct,
                'warehouse_id' => $otherWarehouse->id,
            ]))
            ->assertForbidden();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function companyUser(array $permissions): array
    {
        $company = Company::factory()->create(['name' => 'Liga A']);
        $otherCompany = Company::factory()->create(['name' => 'Liga B']);
        $user = User::factory()->for($company)->create();

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }

    private function warehouseForCompany(Company $company, string $name): Warehouse
    {
        $branch = Branch::factory()->for($company)->create();

        return Warehouse::factory()->for($branch)->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);
    }

    private function pointOfSaleForCompany(Company $company, ?User $user = null): array
    {
        $branch = Branch::factory()->for($company)->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['company_id' => $company->id]);
        $pointOfSale = PointOfSale::factory()->for($branch)->create([
            'company_id' => $company->id,
            'warehouse_id' => $warehouse->id,
        ]);

        if ($user) {
            $pointOfSale->users()->sync([$user->id]);
        }

        return [$pointOfSale, $branch, $warehouse];
    }

    private function saleForRegister(CashRegister $cashRegister, Branch $branch, Warehouse $warehouse, User $user, string $receipt): Sale
    {
        return Sale::query()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'cash_register_id' => $cashRegister->id,
            'point_of_sale_id' => $cashRegister->point_of_sale_id,
            'receipt_number' => $receipt,
            'sequence_number' => random_int(1, 999),
            'sale_date' => now(),
            'subtotal' => 10,
            'discount' => 0,
            'tax' => 0,
            'total' => 10,
            'status' => 'completed',
        ]);
    }

    private function inventoryMovement(Product $product, Warehouse $warehouse, User $user, int $quantity, ?string $notes = null): InventoryMovement
    {
        return InventoryMovement::query()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $user->id,
            'type' => InventoryMovementType::Purchase,
            'quantity' => $quantity,
            'package_quantity' => $quantity,
            'units_per_package' => 1,
            'reference_type' => 'test',
            'notes' => $notes,
        ]);
    }
}
