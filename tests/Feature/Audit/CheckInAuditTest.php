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
