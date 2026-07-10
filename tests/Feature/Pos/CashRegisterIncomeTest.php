<?php

namespace Tests\Feature\Pos;

use App\Models\CashRegister;
use App\Models\Company;
use App\Models\ExtraChargeCategory;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CashRegisterIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_direct_income_from_extra_charge_category(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->extraChargeCategory($user->company_id);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);
        $cashRegister = CashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_amount' => 100,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.incomes.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'detail' => 'Lavado express sin huesped',
                'amount' => 35.50,
                'reference' => 'REC-10',
            ])
            ->assertRedirect(route('pos.index'));

        $this->assertDatabaseHas('sales', [
            'cash_register_id' => $cashRegister->id,
            'user_id' => $user->id,
            'subtotal' => '35.50',
            'total' => '35.50',
            'cash_received' => '35.50',
            'cash_change' => '0.00',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('sale_details', [
            'extra_charge_category_id' => $category->id,
            'product_name' => 'Lavanderia',
            'presentation_name' => 'Cargo extra',
            'quantity' => '1.00',
            'unit_price' => '35.50',
            'subtotal' => '35.50',
        ]);
        $this->assertDatabaseHas('sale_payments', [
            'payment_method_id' => $paymentMethod->id,
            'payment_method_name' => 'Efectivo',
            'amount' => '35.50',
            'reference' => 'REC-10',
        ]);
    }

    public function test_direct_income_requires_open_cash_register(): void
    {
        $user = $this->userWithPosAccess();
        $category = $this->extraChargeCategory($user->company_id);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.incomes.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'detail' => 'Lavado express sin huesped',
                'amount' => 35.50,
            ])
            ->assertSessionHasErrors(['amount'], null, 'cashIncome');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_direct_income_rejects_extra_charge_category_from_another_company(): void
    {
        $user = $this->userWithPosAccess();
        $otherCompany = Company::factory()->create();
        $category = $this->extraChargeCategory($otherCompany->id);
        $paymentMethod = PaymentMethod::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Efectivo',
            'is_active' => true,
        ]);
        CashRegister::factory()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'status' => 'open',
        ]);

        $this
            ->actingAs($user)
            ->post(route('pos.incomes.store'), [
                'extra_charge_category_id' => $category->id,
                'payment_method_id' => $paymentMethod->id,
                'detail' => 'Categoria ajena',
                'amount' => 10,
            ])
            ->assertSessionHasErrors(['extra_charge_category_id'], null, 'cashIncome');

        $this->assertDatabaseCount('sales', 0);
    }

    private function userWithPosAccess(): User
    {
        Permission::findOrCreate('pos.access');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('pos.access');

        return $user;
    }

    private function extraChargeCategory(int $companyId): ExtraChargeCategory
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
