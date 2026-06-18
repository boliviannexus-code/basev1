<?php

namespace Tests\Feature\Spaces;

use App\Models\BathroomType;
use App\Models\Company;
use App\Models\GeneralService;
use App\Models\PrivateSpaceType;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SpaceListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_only_sees_own_spaces_in_listing(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view']);
        $otherCompany = Company::factory()->create();

        $ownSpace = $this->privateDraftSpace($user->company_id, ['title' => 'Casa visible']);
        $foreignSpace = $this->privateDraftSpace($otherCompany->id, ['title' => 'Casa ajena']);

        $this
            ->actingAs($user)
            ->get(route('spaces.index'))
            ->assertOk()
            ->assertSee(route('datatables.spaces'));

        $response = $this
            ->actingAs($user)
            ->getJson(route('datatables.spaces'));

        $response->assertOk();
        $this->assertStringContainsString($ownSpace->title, $response->getContent());
        $this->assertStringNotContainsString($foreignSpace->title, $response->getContent());
    }

    public function test_listing_payload_does_not_expose_shared_space_rooms_as_rows_or_data(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view']);
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::firstOrFail()->id,
            'private_space_type_id' => null,
            'title' => 'Hostal principal',
            'name' => 'Hostal principal',
            'slug' => 'hostal-principal',
            'status' => 'draft',
        ]);

        $space->rooms()->create([
            'company_id' => $user->company_id,
            'title' => 'Habitacion interna invisible',
            'name' => 'Habitacion interna invisible',
            'room_number' => '101',
            'bathroom_type_id' => BathroomType::firstOrFail()->id,
            'status' => 'draft',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('datatables.spaces'));

        $response->assertOk();
        $this->assertSame(1, $response->json('recordsTotal'));
        $this->assertStringContainsString('Hostal principal', $response->getContent());
        $this->assertStringNotContainsString('Habitacion interna invisible', $response->getContent());
    }

    public function test_continue_registration_redirects_to_first_incomplete_step(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view', 'spaces.edit']);
        $space = $this->privateDraftSpace($user->company_id, [
            'title' => 'Casa sin fotos',
            'short_description' => 'Descripcion corta lista',
            'full_description' => 'Descripcion extendida lista',
            'max_capacity' => 4,
            'bedrooms_count' => 2,
            'beds_count' => 2,
        ]);

        $this
            ->actingAs($user)
            ->get(route('spaces.continue', $space))
            ->assertRedirect(route('spaces.private.photos.edit', $space));
    }

    public function test_incomplete_space_cannot_be_activated(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view', 'spaces.edit']);
        $space = $this->privateDraftSpace($user->company_id);

        $this
            ->actingAs($user)
            ->patch(route('spaces.activate', $space))
            ->assertSessionHasErrors('space');

        $this->assertSame('draft', $space->refresh()->status);
    }

    public function test_complete_space_can_be_activated_and_deactivated(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view', 'spaces.edit']);
        $space = $this->completePrivateSpace($user->company_id, [
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.activate', $space))
            ->assertRedirect();

        $this->assertSame('active', $space->refresh()->status);

        $this
            ->actingAs($user)
            ->patch(route('spaces.deactivate', $space))
            ->assertRedirect();

        $this->assertSame('inactive', $space->refresh()->status);
    }

    public function test_active_space_can_be_taken_offline_and_put_online_for_public_reservations(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view', 'spaces.edit']);
        $space = $this->completePrivateSpace($user->company_id, [
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'is_public_online' => true,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.offline', $space))
            ->assertRedirect();

        $space->refresh();
        $this->assertSame('active', $space->status);
        $this->assertFalse($space->is_public_online);

        $this
            ->actingAs($user)
            ->patch(route('spaces.online', $space))
            ->assertRedirect();

        $space->refresh();
        $this->assertSame('active', $space->status);
        $this->assertTrue($space->is_public_online);
    }

    public function test_show_displays_space_detail(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view']);
        $space = $this->completePrivateSpace($user->company_id, [
            'title' => 'Casa detalle',
            'name' => 'Casa detalle',
        ]);

        $this
            ->actingAs($user)
            ->get(route('spaces.show', $space))
            ->assertOk()
            ->assertSee('Casa detalle')
            ->assertSee('Servicios generales');
    }

    public function test_listing_actions_show_continue_without_edit_button(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser(['spaces.view', 'spaces.edit']);
        $space = $this->privateDraftSpace($user->company_id, ['title' => 'Casa accion']);

        $response = $this
            ->actingAs($user)
            ->getJson(route('datatables.spaces'));

        $response->assertOk();
        $actions = $response->json('data.0.actions');
        $this->assertStringContainsString(route('spaces.continue', $space), $actions);
        $this->assertStringContainsString('Continuar', $actions);
        $this->assertStringNotContainsString('Editar', $actions);
    }

    private function companyUser(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function privateDraftSpace(int $companyId, array $attributes = []): Space
    {
        return Space::factory()->create([
            'company_id' => $companyId,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'shared_space_type_id' => null,
            'title' => 'Casa borrador',
            'name' => 'Casa borrador',
            'status' => 'draft',
            ...$attributes,
        ]);
    }

    private function completePrivateSpace(int $companyId, array $attributes = []): Space
    {
        $space = $this->privateDraftSpace($companyId, [
            'title' => 'Casa completa',
            'name' => 'Casa completa',
            'short_description' => 'Descripcion corta completa',
            'full_description' => 'Descripcion extendida completa',
            'max_capacity' => 4,
            'bedrooms_count' => 2,
            'beds_count' => 2,
            ...$attributes,
        ]);

        $space->photos()->create([
            'company_id' => $companyId,
            'path' => 'fake.webp',
            'type' => 'main',
        ]);
        $space->generalServices()->sync([
            GeneralService::firstOrFail()->id => ['company_id' => $companyId],
        ]);
        $space->location()->create([
            'company_id' => $companyId,
            'country' => 'Bolivia',
            'city' => 'La Paz',
            'address' => 'Calle 1',
        ]);

        return $space;
    }
}
