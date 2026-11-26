<?php

namespace Tests\Feature\Pos;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserCompanyAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_cannot_change_another_users_company_but_can_edit_their_name(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $actor = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $target = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        Permission::findOrCreate('users.edit');
        $actor->givePermissionTo('users.edit');
        $data = ['name' => 'Nombre actualizado', 'email' => $target->email, 'company_id' => $other->id, 'is_active' => 1];
        $this->actingAs($actor)->put(route('users.update', $target), $data)->assertSessionHasErrors('company_id');
        $this->assertSame($company->id, $target->refresh()->company_id);
        $data['company_id'] = $company->id;
        $this->actingAs($actor)->put(route('users.update', $target), $data)->assertSessionHasNoErrors();
        $this->assertSame('Nombre actualizado', $target->refresh()->name);
    }

    public function test_global_super_admin_can_change_company(): void
    {
        $company = Company::factory()->create();
        $other = Company::factory()->create();
        $actor = User::factory()->create(['company_id' => null, 'is_active' => true]);
        $target = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        Permission::findOrCreate('users.edit');
        Role::findOrCreate('super_admin')->givePermissionTo('users.edit');
        $actor->assignRole('super_admin');
        $this->actingAs($actor)->put(route('users.update', $target), ['name' => $target->name, 'email' => $target->email, 'company_id' => $other->id, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame($other->id, $target->refresh()->company_id);
    }
}
