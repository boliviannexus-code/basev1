<?php

namespace Tests\Feature\SpaceCash;

use App\Models\Company;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpaceCashHistoryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_and_details_are_limited_to_own_registers(): void
    {
        [$user, $own, $other] = $this->context();

        $this->actingAs($user)->get(route('space-cash.history'))
            ->assertOk()
            ->assertViewHas('cashRegisters', fn ($registers): bool => $registers->pluck('id')->all() === [$own->id]);
        $this->get(route('space-cash.show', $own))->assertOk();
        $this->get(route('space-cash.show', $other))->assertForbidden();
    }

    public function test_role_permission_allows_company_history_and_can_be_revoked(): void
    {
        [$user, $own, $other] = $this->context();
        $role = Role::findOrCreate('cash-supervisor');
        $role->givePermissionTo('space-cash.view-all');
        $user->assignRole($role);

        $this->actingAs($user)->get(route('space-cash.history'))
            ->assertOk()
            ->assertViewHas('cashRegisters', fn ($registers): bool => $registers->count() === 2
                && $registers->pluck('id')->contains($own->id)
                && $registers->pluck('id')->contains($other->id));
        $this->get(route('space-cash.show', $other))->assertOk();

        $role->revokePermissionTo('space-cash.view-all');
        $user->unsetRelation('roles');
        $this->get(route('space-cash.show', $other))->assertForbidden();
    }

    public function test_view_all_never_exposes_other_companies(): void
    {
        [$user] = $this->context();
        $user->givePermissionTo('space-cash.view-all');
        $foreignUser = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $foreign = SpaceCashRegister::factory()->create([
            'company_id' => $foreignUser->company_id,
            'user_id' => $foreignUser->id,
        ]);

        $this->actingAs($user)->get(route('space-cash.history'))
            ->assertOk()
            ->assertViewHas('cashRegisters', fn ($registers): bool => ! $registers->pluck('id')->contains($foreign->id));
        $this->get(route('space-cash.show', $foreign))->assertNotFound();
    }

    public function test_filters_scope_history_by_user_status_and_opening_dates(): void
    {
        [$user, $own, $other] = $this->context();
        $user->givePermissionTo('space-cash.view-all');
        $own->update(['opened_at' => '2026-01-10 10:00:00', 'status' => 'closed']);
        $other->update(['opened_at' => '2026-01-20 10:00:00', 'status' => 'open']);
        $this->actingAs($user)->get(route('space-cash.history', ['user_id' => $other->user_id, 'status' => 'open', 'date_from' => '2026-01-15', 'date_to' => '2026-01-25']))
            ->assertOk()->assertViewHas('cashRegisters', fn ($registers): bool => $registers->pluck('id')->all() === [$other->id]);
        $user->revokePermissionTo('space-cash.view-all');
        $this->get(route('space-cash.history', ['user_id' => $other->user_id]))
            ->assertOk()->assertViewHas('cashRegisters', fn ($registers): bool => $registers->isEmpty());
        $this->get(route('space-cash.history', ['date_to' => '2026-01-15']))->assertOk();
    }

    private function context(): array
    {
        foreach (['space-cash.access', 'space-cash.view', 'space-cash.view-all', 'occupancy.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        // Occupancy management must not bypass the restriction on other users' cash.
        $user->givePermissionTo(['space-cash.access', 'space-cash.view', 'occupancy.manage']);
        $otherUser = User::factory()->create(['company_id' => $company->id]);
        $own = SpaceCashRegister::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $other = SpaceCashRegister::factory()->create(['company_id' => $company->id, 'user_id' => $otherUser->id]);

        return [$user, $own, $other];
    }
}
