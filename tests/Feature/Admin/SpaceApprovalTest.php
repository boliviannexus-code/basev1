<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\PrivateSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpaceApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_approve_completed_space(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $superAdmin = $this->superAdmin();
        $space = $this->completedSpace();

        $this
            ->actingAs($superAdmin)
            ->get(route('admin.spaces.approvals'))
            ->assertOk()
            ->assertSee('Casa terminada');

        $this
            ->actingAs($superAdmin)
            ->get(route('admin.spaces.show', $space))
            ->assertOk()
            ->assertSee('Aprobar alojamiento')
            ->assertSee('Enviar correcciones');

        $this
            ->actingAs($superAdmin)
            ->patch(route('admin.spaces.approve', $space))
            ->assertRedirect();

        $space->refresh();
        $this->assertSame('approved', $space->status);
        $this->assertSame($superAdmin->id, $space->approved_by);
        $this->assertNotNull($space->approved_at);
        $this->assertDatabaseHas('space_review_notes', [
            'space_id' => $space->id,
            'user_id' => $superAdmin->id,
            'type' => 'approval',
            'message' => 'Alojamiento aprobado.',
        ]);
    }

    public function test_super_admin_can_request_corrections_and_company_can_see_history(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        Permission::findOrCreate('spaces.view');
        Permission::findOrCreate('spaces.create');
        $superAdmin = $this->superAdmin();
        $company = Company::factory()->create();
        $companyUser = User::factory()->create(['company_id' => $company->id]);
        $companyUser->givePermissionTo(['spaces.view', 'spaces.create']);
        $space = $this->completedSpace($company->id);
        $message = 'Actualizar la descripcion y revisar las fotografias principales.';

        $this
            ->actingAs($superAdmin)
            ->patch(route('admin.spaces.corrections', $space), [
                'message' => $message,
            ])
            ->assertRedirect();

        $space->refresh();
        $this->assertSame('needs_corrections', $space->status);
        $this->assertNull($space->approved_by);
        $this->assertNull($space->approved_at);
        $this->assertDatabaseHas('space_review_notes', [
            'space_id' => $space->id,
            'user_id' => $superAdmin->id,
            'type' => 'correction',
            'message' => $message,
        ]);

        $this
            ->actingAs($companyUser)
            ->get(route('spaces.show', $space))
            ->assertOk()
            ->assertSee('Con correcciones')
            ->assertSee($message);

        $this
            ->actingAs($companyUser)
            ->get(route('spaces.private.details.edit', $space))
            ->assertOk();
    }

    public function test_approved_space_can_be_modified_from_stepper(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        Permission::findOrCreate('spaces.create');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('spaces.create');
        $space = $this->completedSpace($company->id, [
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->get(route('spaces.private.details.edit', $space))
            ->assertOk()
            ->assertSee('Casa terminada');
    }

    public function test_super_admin_can_suspend_active_space_and_send_it_to_review(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        Permission::findOrCreate('spaces.view');
        $superAdmin = $this->superAdmin();
        $company = Company::factory()->create();
        $companyUser = User::factory()->create(['company_id' => $company->id]);
        $companyUser->givePermissionTo('spaces.view');
        $space = $this->completedSpace($company->id, [
            'status' => 'active',
            'approved_by' => $superAdmin->id,
            'approved_at' => now(),
        ]);
        $message = 'Suspension temporal por revision de datos sensibles del alojamiento.';

        $this
            ->actingAs($superAdmin)
            ->get(route('admin.spaces.show', $space))
            ->assertOk()
            ->assertSee('Suspender y enviar a revision');

        $this
            ->actingAs($superAdmin)
            ->patch(route('admin.spaces.suspend-review', $space), [
                'message' => $message,
            ])
            ->assertRedirect();

        $space->refresh();
        $this->assertSame('in_review', $space->status);
        $this->assertNull($space->approved_by);
        $this->assertNull($space->approved_at);
        $this->assertDatabaseHas('space_review_notes', [
            'space_id' => $space->id,
            'user_id' => $superAdmin->id,
            'type' => 'suspension',
            'message' => $message,
        ]);

        $this
            ->actingAs($companyUser)
            ->get(route('spaces.show', $space))
            ->assertOk()
            ->assertSee('En revision')
            ->assertSee($message);
    }

    private function superAdmin(): User
    {
        Permission::findOrCreate('spaces.approve');
        $role = Role::findOrCreate('super_admin');
        $role->givePermissionTo('spaces.approve');

        $user = User::factory()->create(['company_id' => null]);
        $user->assignRole($role);

        return $user;
    }

    private function completedSpace(?int $companyId = null, array $attributes = []): Space
    {
        $companyId ??= Company::factory()->create()->id;

        return Space::factory()->create([
            'company_id' => $companyId,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'shared_space_type_id' => null,
            'title' => 'Casa terminada',
            'name' => 'Casa terminada',
            'status' => 'completed',
            ...$attributes,
        ]);
    }
}
