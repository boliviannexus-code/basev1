<?php

namespace Tests\Feature\CheckIns;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\AvailabilityStatus;
use App\Models\CashRegister;
use App\Models\CashRegisterLodgingPayment;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashRegister;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeleteCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_deletes_group_and_cancels_payments_and_releases_availability(): void
    {
        Permission::findOrCreate('occupancy.manage');
        $role = Role::findOrCreate('super_admin');
        $role->givePermissionTo('occupancy.manage');
        $stay = Stay::factory()->create();
        $group = $stay->checkInGroup;
        $admin = User::factory()->create(['company_id' => $stay->company_id]);
        $admin->assignRole($role);
        $register = SpaceCashRegister::factory()->create(['company_id' => $stay->company_id, 'user_id' => $admin->id]);
        $method = PaymentMethod::create(['company_id' => $stay->company_id, 'name' => 'Efectivo', 'is_active' => true]);
        $statement = AccountStatement::create(['company_id' => $stay->company_id, 'stay_id' => $stay->id, 'currency' => 'BOB', 'subtotal' => 100, 'discount_total' => 0, 'extra_charges_total' => 0, 'payments_total' => 40, 'balance' => 60, 'status' => 'partial']);
        $item = AccountStatementItem::create(['company_id' => $stay->company_id, 'account_statement_id' => $statement->id, 'stay_id' => $stay->id, 'date' => today(), 'type' => 'payment', 'description' => 'Pago', 'quantity' => 1, 'unit_price' => 40, 'total' => 40, 'currency' => 'BOB', 'source' => 'manual', 'status' => 'active']);
        $payment = SpaceCashLodgingPayment::create(['company_id' => $stay->company_id, 'space_cash_register_id' => $register->id, 'user_id' => $admin->id, 'account_statement_id' => $statement->id, 'account_statement_item_id' => $item->id, 'stay_id' => $stay->id, 'check_in_group_id' => $group->id, 'payment_method_id' => $method->id, 'receipt_number' => 'TEST-1', 'amount_original' => 40, 'currency_original' => 'BOB', 'exchange_rate' => 1, 'amount_bob' => 40, 'status' => 'active']);
        $posRegister = CashRegister::factory()->create(['company_id' => $stay->company_id, 'user_id' => $admin->id]);
        $posPayment = CashRegisterLodgingPayment::create(['company_id' => $stay->company_id, 'cash_register_id' => $posRegister->id, 'user_id' => $admin->id, 'account_statement_id' => $statement->id, 'account_statement_item_id' => $item->id, 'stay_id' => $stay->id, 'check_in_group_id' => $group->id, 'payment_method_id' => $method->id, 'receipt_number' => 'POS-TEST-1', 'amount_original' => 40, 'currency_original' => 'BOB', 'exchange_rate' => 1, 'amount_bob' => 40, 'status' => 'active']);
        $second = Stay::factory()->create(['company_id' => $stay->company_id, 'check_in_group_id' => $group->id]);
        $status = AvailabilityStatus::create(['company_id' => $stay->company_id, 'space_id' => $stay->space_id, 'date' => today(), 'status' => 'occupied', 'source' => 'check_in']);
        $this->actingAs($admin)->delete(route('check-ins.destroy', $group))->assertRedirect(route('occupancy.index'));
        $this->assertSoftDeleted('check_in_groups', ['id' => $group->id]);
        $this->assertSoftDeleted('stays', ['id' => $stay->id]);
        $this->assertSoftDeleted('stays', ['id' => $second->id]);
        $this->assertSoftDeleted('availability_statuses', ['id' => $status->id]);
        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('cancelled', $posPayment->fresh()->status);
        $this->assertSame('cancelled', $item->fresh()->status);
        $this->assertEquals(0, $statement->fresh()->balance);
    }

    public function test_deleting_a_group_preserves_other_occupancy_and_reservation_statuses(): void
    {
        Permission::findOrCreate('occupancy.manage');
        $role = Role::findOrCreate('super_admin');
        $role->givePermissionTo('occupancy.manage');
        $stay = Stay::factory()->create();
        $admin = User::factory()->create(['company_id' => $stay->company_id]);
        $admin->assignRole($role);
        $other = Stay::factory()->create(['company_id' => $stay->company_id, 'space_id' => $stay->space_id, 'check_in_date' => $stay->check_in_date, 'check_out_date' => $stay->check_out_date]);
        $occupied = AvailabilityStatus::create(['company_id' => $stay->company_id, 'space_id' => $stay->space_id, 'date' => today(), 'status' => 'occupied', 'source' => 'check_in']);
        $reserved = AvailabilityStatus::create(['company_id' => $stay->company_id, 'space_id' => $stay->space_id, 'date' => today()->addDay(), 'status' => 'reserved', 'source' => 'reservation']);

        $this->actingAs($admin)->delete(route('check-ins.destroy', $stay->checkInGroup))->assertRedirect();
        $this->assertDatabaseHas('stays', ['id' => $other->id, 'status' => 'occupied', 'deleted_at' => null]);
        $this->assertDatabaseHas('availability_statuses', ['id' => $occupied->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('availability_statuses', ['id' => $reserved->id, 'deleted_at' => null]);
    }

    public function test_non_super_admin_and_other_company_cannot_delete(): void
    {
        Permission::findOrCreate('occupancy.manage');
        $role = Role::findOrCreate('super_admin');
        $role->givePermissionTo('occupancy.manage');
        $stay = Stay::factory()->create();
        $user = User::factory()->create(['company_id' => $stay->company_id]);
        $user->givePermissionTo('occupancy.manage');
        $this->actingAs($user)->delete(route('check-ins.destroy', $stay->checkInGroup))->assertForbidden();
        $other = User::factory()->create(['company_id' => Company::factory()]);
        $other->assignRole($role);
        $this->actingAs($other)->delete(route('check-ins.destroy', $stay->checkInGroup))->assertNotFound();
        $this->assertDatabaseHas('stays', ['id' => $stay->id, 'deleted_at' => null, 'status' => 'occupied']);
    }
}
