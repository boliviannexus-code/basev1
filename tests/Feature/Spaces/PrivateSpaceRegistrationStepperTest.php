<?php

namespace Tests\Feature\Spaces;

use App\Models\Company;
use App\Models\GeneralService;
use App\Models\PrivateSpaceType;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PrivateSpaceRegistrationStepperTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_complete_private_space_stepper_and_publish(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();

        $this
            ->actingAs($user)
            ->post(route('spaces.private.modality.store'), ['space_mode' => 'privado'])
            ->assertRedirect();

        $space = Space::query()->where('company_id', $user->company_id)->firstOrFail();
        $privateType = PrivateSpaceType::where('slug', 'casa')->firstOrFail();

        $this
            ->actingAs($user)
            ->put(route('spaces.private.details.store', $space), [
                'private_space_type_id' => $privateType->id,
                'title' => 'Casa frente al lago',
                'max_capacity' => 6,
                'bedrooms_count' => 3,
                'beds_count' => 4,
                'private_bathrooms_count' => 2,
                'shared_bathrooms_count' => 0,
            ])
            ->assertRedirect(route('spaces.private.descriptions.edit', $space));

        $this
            ->actingAs($user)
            ->put(route('spaces.private.descriptions.store', $space), [
                'short_description' => str_repeat('Descripcion corta completa ', 5),
                'full_description' => str_repeat('Descripcion extendida con informacion suficiente para publicar el alojamiento privado. ', 5),
            ])
            ->assertRedirect(route('spaces.private.photos.edit', $space));

        $this
            ->actingAs($user)
            ->put(route('spaces.private.photos.store', $space), [
                'main_photo' => UploadedFile::fake()->image('principal.jpg', 1200, 800)->size(600),
                'gallery_photos' => [
                    UploadedFile::fake()->image('galeria.png', 900, 700)->size(400),
                ],
            ])
            ->assertRedirect(route('spaces.private.services.edit', $space));

        $serviceIds = GeneralService::active()->limit(2)->pluck('id')->all();

        $this
            ->actingAs($user)
            ->put(route('spaces.private.services.store', $space), [
                'general_services' => $serviceIds,
            ])
            ->assertRedirect(route('spaces.private.location.edit', $space));

        $this
            ->actingAs($user)
            ->put(route('spaces.private.location.store', $space), [
                'country' => 'Bolivia',
                'state_or_region' => 'La Paz',
                'city' => 'Copacabana',
                'zone_or_neighborhood' => 'Centro',
                'address' => 'Av. Costanera 123',
                'address_text' => 'Av. Costanera 123',
                'reference' => 'A dos cuadras del muelle',
                'reference_text' => 'A dos cuadras del muelle',
                'latitude' => '-16.1650000',
                'longitude' => '-69.0850000',
                'google_place_id' => 'place-private-123',
            ])
            ->assertRedirect(route('spaces.private.review', $space));

        $this
            ->actingAs($user)
            ->patch(route('spaces.private.publish', $space))
            ->assertRedirect(route('spaces.private.review', $space));

        $space->refresh();

        $this->assertSame('completed', $space->status);
        $this->assertSame('casa-frente-al-lago', $space->slug);
        $this->assertSame(0, $space->rooms()->count());
        $this->assertCount(2, $space->photos);
        $this->assertTrue($space->photos->every(fn ($photo): bool => str_ends_with($photo->path, '.webp')));
        Storage::disk('public')->assertExists($space->photos->firstWhere('type', 'main')->path);
        $this->assertDatabaseHas('space_general_service', [
            'company_id' => $user->company_id,
            'space_id' => $space->id,
            'general_service_id' => $serviceIds[0],
        ]);
        $this->assertDatabaseHas('space_locations', [
            'company_id' => $user->company_id,
            'space_id' => $space->id,
            'city' => 'Copacabana',
            'address_text' => 'Av. Costanera 123',
            'google_place_id' => 'place-private-123',
        ]);
    }

    public function test_private_space_cannot_publish_without_main_photo(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'title' => 'Casa sin foto',
            'short_description' => str_repeat('Descripcion corta completa ', 5),
            'full_description' => str_repeat('Descripcion extendida con informacion suficiente para publicar. ', 6),
            'max_capacity' => 4,
            'bedrooms_count' => 2,
            'beds_count' => 2,
            'private_bathrooms_count' => 1,
            'shared_bathrooms_count' => 0,
        ]);

        $space->location()->create([
            'company_id' => $space->company_id,
            'country' => 'Bolivia',
            'city' => 'La Paz',
            'address' => 'Calle 1',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.private.publish', $space))
            ->assertSessionHasErrors('space');

        $this->assertSame('draft', $space->refresh()->status);
    }

    public function test_private_space_can_publish_without_photos_when_photos_are_skipped(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'title' => 'Casa sin fotos por decision',
            'short_description' => str_repeat('Descripcion corta completa ', 5),
            'full_description' => str_repeat('Descripcion extendida con informacion suficiente para publicar. ', 6),
            'max_capacity' => 4,
            'bedrooms_count' => 2,
            'beds_count' => 2,
            'private_bathrooms_count' => 1,
            'shared_bathrooms_count' => 0,
            'photos_skipped' => true,
        ]);

        $space->location()->create([
            'company_id' => $space->company_id,
            'country' => 'Bolivia',
            'city' => 'La Paz',
            'address' => 'Calle 1',
        ]);
        $space->generalServices()->sync([
            GeneralService::firstOrFail()->id => ['company_id' => $space->company_id],
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.private.publish', $space))
            ->assertRedirect(route('spaces.private.review', $space));

        $this->assertSame('completed', $space->refresh()->status);
    }

    public function test_private_space_gallery_is_limited_to_five_extra_photos(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
        ]);

        $this
            ->actingAs($user)
            ->put(route('spaces.private.photos.store', $space), [
                'gallery_photos' => collect(range(1, 6))
                    ->map(fn (int $index) => UploadedFile::fake()->image("extra-{$index}.jpg"))
                    ->all(),
            ])
            ->assertSessionHasErrors('gallery_photos');
    }

    public function test_title_must_be_unique_inside_company(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $privateType = PrivateSpaceType::where('slug', 'casa')->firstOrFail();

        Space::factory()->create([
            'company_id' => $user->company_id,
            'title' => 'Casa repetida',
        ]);

        $draft = Space::factory()->create([
            'company_id' => $user->company_id,
            'title' => null,
        ]);

        $this
            ->actingAs($user)
            ->put(route('spaces.private.details.store', $draft), [
                'private_space_type_id' => $privateType->id,
                'title' => 'Casa repetida',
                'max_capacity' => 3,
                'bedrooms_count' => 1,
                'beds_count' => 1,
                'private_bathrooms_count' => 1,
                'shared_bathrooms_count' => 0,
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_company_user_can_delete_uploaded_private_space_photo(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
        ]);
        Storage::disk('public')->put('accommodations/company-1/spaces/1/test.webp', 'fake');
        $photo = $space->photos()->create([
            'company_id' => $user->company_id,
            'path' => 'accommodations/company-1/spaces/1/test.webp',
            'type' => 'gallery',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('spaces.private.photos.destroy', [$space, $photo]))
            ->assertRedirect();

        $this->assertDatabaseMissing('space_photos', ['id' => $photo->id]);
        Storage::disk('public')->assertMissing('accommodations/company-1/spaces/1/test.webp');
    }

    public function test_private_photo_page_shows_character_counters_and_delete_button(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
        ]);
        $photo = $space->photos()->create([
            'company_id' => $user->company_id,
            'path' => 'fake.webp',
            'type' => 'gallery',
        ]);

        $this
            ->actingAs($user)
            ->get(route('spaces.private.descriptions.edit', $space))
            ->assertOk()
            ->assertSee('data-character-counter', false);

        $this
            ->actingAs($user)
            ->get(route('spaces.private.photos.edit', $space))
            ->assertOk()
            ->assertSee(route('spaces.private.photos.destroy', [$space, $photo]), false)
            ->assertSee('space-photo-delete', false);
    }

    private function companyUserWithSpacePermission(): User
    {
        Permission::findOrCreate('spaces.create');

        $user = User::factory()->create([
            'company_id' => Company::factory()->create()->id,
        ]);
        $user->givePermissionTo('spaces.create');

        return $user;
    }
}
