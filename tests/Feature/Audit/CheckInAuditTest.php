<?php

namespace Tests\Feature\Audit;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckInAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_check_in_audit_preserves_creator_time_and_subsequent_editor(): void
    {
        config(['audit.console' => true]);
        Permission::findOrCreate('audits.view');
        $company = Company::factory()->create();
        $creator = User::factory()->create(['company_id' => $company->id]);
        $editor = User::factory()->create(['company_id' => $company->id]);
        $creator->givePermissionTo('audits.view');
        $this->travelTo(now()->setTime(9, 15, 30));
        $registeredAt = now()->format('Y-m-d H:i:s');
        $this->actingAs($creator);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'created_by' => $creator->id,
        ]);
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
        ]);

        $this->travel(2)->hours();
        $this->actingAs($editor);
        $stay->update(['notes' => 'Cambio de estancia']);

        $created = Audit::query()->where('auditable_type', CheckInGroup::class)
            ->where('auditable_id', $group->id)->where('event', 'created')->firstOrFail();
        $this->assertSame($creator->id, (int) $created->user_id);
        $this->assertSame($registeredAt, $created->created_at->format('Y-m-d H:i:s'));
        $this->assertSame($group->code, $created->new_values['code']);
        $this->assertDatabaseHas('audits', [
            'auditable_type' => Stay::class,
            'auditable_id' => $stay->id,
            'event' => 'updated',
            'user_id' => $editor->id,
            'company_id' => $company->id,
        ]);

        $this->actingAs($creator)->getJson(route('datatables.audits', [
            'draw' => 1,
            'auditable_type' => CheckInGroup::class,
            'event' => 'created',
            'user_id' => $creator->id,
        ]))->assertOk()
            ->assertJsonFragment(['auditable_label' => 'Check-ins', 'user_name' => $creator->name, 'created_at' => $registeredAt]);
    }

    public function test_room_check_in_and_payments_have_readable_details(): void
    {
        config(['audit.console' => true]);
        Permission::findOrCreate('audits.view');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('audits.view');
        $this->actingAs($user);
        $space = \App\Models\Space::factory()->create(['company_id' => $company->id, 'title' => 'Hotel Sol']);
        $room = \App\Models\SpaceRoom::factory()->create(['company_id' => $company->id, 'space_id' => $space->id, 'name' => 'Habitación 101']);
        $group = CheckInGroup::factory()->create(['company_id' => $company->id]);
        $stay = Stay::factory()->create(['company_id' => $company->id, 'check_in_group_id' => $group->id, 'space_id' => $space->id, 'space_room_id' => $room->id]);
        $audit = Audit::query()->where('auditable_type', Stay::class)->where('auditable_id', $stay->id)->where('event', 'created')->firstOrFail();
        $this->get(route('audits.show', $audit))->assertOk()->assertSee($company->name)->assertSee('Habitación 101')->assertSee('Precio por noche (BOB)')->assertSee('Registrado el')->assertSee('Check-in registrado')->assertDontSee('#'.$room->id);
        $this->getJson(route('datatables.audits', ['room_id' => $room->id]))->assertOk()->assertJsonFragment(['changes' => 'Check-in registrado · Empresa, Check-in']);
        $method = \App\Models\PaymentMethod::query()->create(['company_id' => $company->id, 'name' => 'Efectivo', 'is_active' => true]);
        $register = \App\Models\SpaceCashRegister::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $payment = \App\Models\SpaceCashIncome::query()->create(['company_id' => $company->id, 'space_cash_register_id' => $register->id, 'user_id' => $user->id, 'payment_method_id' => $method->id, 'receipt_number' => 'TEST-01', 'detail' => 'Ingreso', 'amount' => 125.50]);
        $paymentAudit = Audit::query()->where('auditable_type', \App\Models\SpaceCashIncome::class)->where('auditable_id', $payment->id)->firstOrFail();
        $this->get(route('audits.show', $paymentAudit))->assertOk()->assertSee('125.50 BOB')->assertSee('Método de pago')->assertSee('Efectivo');
    }

    public function test_audit_formats_amount_using_audited_company_currency(): void
    {
        config(['audit.console' => true]);
        Permission::findOrCreate('audits.view');
        $company = Company::factory()->create(['local_currency' => 'PEN']);
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('audits.view');
        $this->actingAs($user);
        $group = CheckInGroup::factory()->create(['company_id' => $company->id]);
        $stay = Stay::factory()->create(['company_id' => $company->id, 'check_in_group_id' => $group->id, 'price_per_night_bob' => 125]);
        $audit = Audit::query()->where('auditable_type', Stay::class)->where('auditable_id', $stay->id)->where('event', 'created')->firstOrFail();
        $this->get(route('audits.show', $audit))->assertOk()->assertSee('Precio por noche (PEN)')->assertSee('125.00 PEN');
    }

    public function test_check_in_audit_is_hidden_from_other_companies(): void
    {
        config(['audit.console' => true]);
        Permission::findOrCreate('audits.view');
        $creator = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $viewer = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $viewer->givePermissionTo('audits.view');
        $this->actingAs($creator);
        $group = CheckInGroup::factory()->create(['company_id' => $creator->company_id, 'created_by' => $creator->id]);
        $audit = Audit::query()->where('auditable_type', CheckInGroup::class)
            ->where('auditable_id', $group->id)->firstOrFail();

        $this->actingAs($viewer)->get(route('audits.show', $audit))->assertForbidden();
        $this->getJson(route('datatables.audits', ['auditable_type' => CheckInGroup::class]))
            ->assertOk()->assertJsonPath('recordsTotal', 0);
    }
}
