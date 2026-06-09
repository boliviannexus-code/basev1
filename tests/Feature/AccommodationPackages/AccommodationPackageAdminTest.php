<?php

namespace Tests\Feature\AccommodationPackages;

use App\Models\AccommodationPackage;
use App\Models\Company;
use App\Models\PackageService;
use App\Models\PrivateSpaceType;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AccommodationPackageAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_create_package_for_private_spaces_with_services(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser();
        $space = $this->privateSpace($user->company_id);
        $service = PackageService::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Cena incluida',
            'type' => 'alimentacion',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->post(route('accommodation-packages.store'), [
                'name' => 'Romance premium',
                'short_description' => 'Paquete todo incluido para dos personas.',
                'price' => 550,
                'currency' => 'BOB',
                'included_people' => 2,
                'max_people' => 3,
                'extra_person_price' => 120,
                'requires_full_private_space' => 1,
                'nights_included' => 1,
                'is_active' => 1,
                'space_ids' => [$space->id],
                'services' => [
                    [
                        'service_id' => $service->id,
                        'inclusion_type' => 'included',
                        'sort_order' => 1,
                    ],
                ],
            ])
            ->assertRedirect(route('accommodation-packages.index'));

        $this->assertDatabaseHas('accommodation_packages', [
            'company_id' => $user->company_id,
            'name' => 'Romance premium',
            'price' => '550.00',
            'included_people' => 2,
        ]);
        $this->assertDatabaseHas('accommodation_package_space', [
            'space_id' => $space->id,
        ]);
        $this->assertDatabaseHas('accommodation_package_service', [
            'service_id' => $service->id,
            'inclusion_type' => 'included',
        ]);
    }

    public function test_package_rejects_shared_spaces_and_requires_extra_person_price_when_needed(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser();
        $sharedSpace = $this->sharedSpace($user->company_id);

        $this
            ->actingAs($user)
            ->post(route('accommodation-packages.store'), [
                'name' => 'Familiar',
                'short_description' => 'Paquete para familia.',
                'price' => 700,
                'currency' => 'BOB',
                'included_people' => 2,
                'max_people' => 4,
                'nights_included' => 1,
                'is_active' => 1,
                'space_ids' => [$sharedSpace->id],
            ])
            ->assertSessionHasErrors(['space_ids', 'extra_person_price']);
    }

    public function test_company_user_can_copy_package_with_spaces_and_services(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser();
        $space = $this->privateSpace($user->company_id);
        $service = PackageService::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Cena incluida',
            'type' => 'alimentacion',
            'is_active' => true,
        ]);
        $package = AccommodationPackage::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Romance premium',
            'slug' => 'romance-premium',
            'short_description' => 'Paquete todo incluido para dos personas.',
            'badges' => ['Ideal para pareja'],
            'price' => 550,
            'currency' => 'BOB',
            'price_display_text' => '550 Bs la pareja',
            'included_people' => 2,
            'max_people' => 3,
            'extra_person_price' => 120,
            'requires_full_private_space' => true,
            'nights_included' => 1,
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 4,
        ]);
        $package->spaces()->attach($space->id);
        $package->services()->attach($service->id, [
            'inclusion_type' => 'included',
            'sort_order' => 1,
        ]);

        $this
            ->actingAs($user)
            ->post(route('accommodation-packages.copy', $package))
            ->assertRedirect();

        $copy = AccommodationPackage::query()
            ->where('company_id', $user->company_id)
            ->where('name', 'Romance premium - copia')
            ->firstOrFail();

        $this->assertFalse($copy->is_active);
        $this->assertFalse($copy->is_featured);
        $this->assertSame('550.00', $copy->price);
        $this->assertSame(['Ideal para pareja'], $copy->badges);
        $this->assertDatabaseHas('accommodation_package_space', [
            'package_id' => $copy->id,
            'space_id' => $space->id,
        ]);
        $this->assertDatabaseHas('accommodation_package_service', [
            'package_id' => $copy->id,
            'service_id' => $service->id,
            'inclusion_type' => 'included',
            'sort_order' => 1,
        ]);
    }

    public function test_company_user_can_delete_only_inactive_packages(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUser();
        $activePackage = AccommodationPackage::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Activo',
            'slug' => 'activo',
            'short_description' => 'Paquete activo.',
            'price' => 100,
            'currency' => 'BOB',
            'included_people' => 1,
            'requires_full_private_space' => true,
            'nights_included' => 1,
            'is_active' => true,
        ]);
        $inactivePackage = AccommodationPackage::query()->create([
            'company_id' => $user->company_id,
            'name' => 'Inactivo',
            'slug' => 'inactivo',
            'short_description' => 'Paquete inactivo.',
            'price' => 100,
            'currency' => 'BOB',
            'included_people' => 1,
            'requires_full_private_space' => true,
            'nights_included' => 1,
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('accommodation-packages.destroy', $activePackage))
            ->assertRedirect();

        $this->assertDatabaseHas('accommodation_packages', [
            'id' => $activePackage->id,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('accommodation-packages.destroy', $inactivePackage))
            ->assertRedirect();

        $this->assertDatabaseMissing('accommodation_packages', [
            'id' => $inactivePackage->id,
        ]);
    }

    private function companyUser(): User
    {
        Permission::findOrCreate('spaces.view');
        Permission::findOrCreate('spaces.edit');

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo(['spaces.view', 'spaces.edit']);

        return $user;
    }

    private function privateSpace(int $companyId): Space
    {
        return Space::factory()->create([
            'company_id' => $companyId,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'shared_space_type_id' => null,
            'title' => 'Casa privada',
            'status' => 'active',
        ]);
    }

    private function sharedSpace(int $companyId): Space
    {
        return Space::factory()->create([
            'company_id' => $companyId,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'private_space_type_id' => null,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hotel')->firstOrFail()->id,
            'name' => 'Hotel compartido',
            'status' => 'active',
        ]);
    }
}
