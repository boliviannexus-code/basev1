<?php

namespace Tests\Feature\Spaces;

use App\Models\BathroomType;
use App\Models\BedType;
use App\Models\Company;
use App\Models\GeneralService;
use App\Models\Reservation;
use App\Models\ReservationRoom;
use App\Models\RoomBedUnit;
use App\Models\RoomService;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SharedSpaceRegistrationStepperTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_complete_shared_space_stepper_and_publish(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.modality.store'), ['space_mode' => 'compartido'])
            ->assertRedirect();

        $space = Space::query()->where('company_id', $user->company_id)->firstOrFail();
        $sharedType = SharedSpaceType::where('slug', 'hostal')->firstOrFail();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.details.store', $space), [
                'shared_space_type_id' => $sharedType->id,
                'name' => 'Hostal Lago Azul',
                'short_description' => str_repeat('Descripcion corta compartida ', 5),
                'full_description' => str_repeat('Descripcion extendida del alojamiento compartido con datos completos. ', 6),
            ])
            ->assertRedirect(route('spaces.shared.rooms.edit', $space));

        $bathroomType = BathroomType::where('slug', 'privado')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.rooms.store', $space), [
                'name' => 'Habitacion 101',
                'bathroom_type_id' => $bathroomType->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $room = $space->rooms()->firstOrFail();
        $this->assertSame('Habitacion 101', $room->title);
        $bedType = BedType::where('slug', 'cama-matrimonial')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.beds.store', [$space, $room]), [
                'bed_type_id' => $bedType->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $roomServiceIds = RoomService::active()->limit(2)->pluck('id')->all();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.room-services.store', [$space, $room]), [
                'room_services' => $roomServiceIds,
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.photos.store', $space), [
                'main_photo' => UploadedFile::fake()->image('hostal.jpg', 1200, 800)->size(600),
            ])
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.room-photos.store', [$space, $room]), [
                'main_photo' => UploadedFile::fake()->image('room.png', 900, 700)->size(400),
            ])
            ->assertRedirect();

        $generalServiceIds = GeneralService::active()->limit(2)->pluck('id')->all();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.services.store', $space), [
                'general_services' => $generalServiceIds,
            ])
            ->assertRedirect(route('spaces.shared.location.edit', $space));

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.location.store', $space), [
                'country' => 'Bolivia',
                'state_or_region' => 'La Paz',
                'city' => 'Copacabana',
                'zone_or_neighborhood' => 'Centro',
                'address' => 'Av. Costanera 456',
                'address_text' => 'Av. Costanera 456',
                'reference' => 'Frente al lago',
                'reference_text' => 'Frente al lago',
                'latitude' => '-16.1650000',
                'longitude' => '-69.0850000',
                'google_place_id' => 'place-shared-456',
            ])
            ->assertRedirect(route('spaces.shared.review', $space));

        $this
            ->actingAs($user)
            ->patch(route('spaces.shared.publish', $space))
            ->assertRedirect(route('spaces.shared.review', $space));

        $space->refresh();
        $room->refresh();

        $this->assertSame('completed', $space->status);
        $this->assertSame('hostal-lago-azul', $space->slug);
        $this->assertSame(4, $room->max_capacity);
        $this->assertSame(4, $space->max_capacity);
        $this->assertSame(1, $space->bedrooms_count);
        $this->assertSame(2, $space->beds_count);
        $this->assertCount(1, $space->photos);
        $this->assertCount(1, $room->photos);
        $this->assertTrue($space->photos->every(fn ($photo): bool => str_ends_with($photo->path, '.webp')));
        $this->assertTrue($room->photos->every(fn ($photo): bool => str_ends_with($photo->path, '.webp')));
        Storage::disk('public')->assertExists($space->photos->first()->path);
        Storage::disk('public')->assertExists($room->photos->first()->path);
        $this->assertDatabaseHas('room_room_service', [
            'company_id' => $user->company_id,
            'space_room_id' => $room->id,
            'room_service_id' => $roomServiceIds[0],
        ]);
    }

    public function test_shared_space_cannot_publish_without_rooms_and_beds(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hotel')->firstOrFail()->id,
            'private_space_type_id' => null,
            'name' => 'Hotel sin habitaciones',
            'short_description' => str_repeat('Descripcion corta compartida ', 5),
            'full_description' => str_repeat('Descripcion extendida compartida suficiente para validar. ', 7),
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.shared.publish', $space))
            ->assertSessionHasErrors('space');

        $this->assertSame('draft', $space->refresh()->status);
    }

    public function test_shared_review_names_the_missing_general_and_room_photos(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user, 1);
        $bedType = BedType::where('slug', 'cama-matrimonial')->firstOrFail();

        $space->update([
            'name' => 'Hostal sin imagenes',
            'title' => 'Hostal sin imagenes',
            'short_description' => 'Breve',
            'full_description' => 'Descripcion breve.',
        ]);
        $room->beds()->create([
            'company_id' => $user->company_id,
            'bed_type_id' => $bedType->id,
            'quantity' => 1,
            'capacity_per_bed' => $bedType->capacity,
            'total_capacity' => $bedType->capacity,
        ]);
        $space->location()->create([
            'company_id' => $space->company_id,
            'country' => 'Bolivia',
            'city' => 'La Paz',
            'address' => 'Calle 1',
        ]);

        $this
            ->actingAs($user)
            ->get(route('spaces.shared.review', $space))
            ->assertOk()
            ->assertSee('Completa los siguientes datos: foto principal del alojamiento, fotos de habitaciones.');

        $this
            ->actingAs($user)
            ->patch(route('spaces.shared.publish', $space))
            ->assertSessionHasErrors([
                'space' => 'Faltan datos obligatorios para publicar: foto principal del alojamiento, fotos de habitaciones.',
            ]);
    }

    public function test_shared_space_descriptions_only_require_a_maximum_length(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
        ]);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.details.store', $space), [
                'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
                'name' => 'Hostal breve',
                'short_description' => 'Breve',
                'full_description' => 'Descripcion breve.',
            ])
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('spaces.shared.rooms.edit', $space));

        $this->assertDatabaseHas('spaces', [
            'id' => $space->id,
            'short_description' => 'Breve',
            'full_description' => 'Descripcion breve.',
        ]);
    }

    public function test_deleting_shared_room_bed_also_removes_physical_bed_units(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user, 1);
        $bedType = BedType::where('slug', 'cama-matrimonial')->firstOrFail();

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.beds.store', [$space, $room]), [
                'bed_type_id' => $bedType->id,
                'quantity' => 2,
            ])
            ->assertRedirect();

        $bed = $room->beds()->firstOrFail();
        $unitIds = $bed->bedUnits()->pluck('id')->all();

        $this->assertCount(2, $unitIds);

        $this
            ->actingAs($user)
            ->delete(route('spaces.shared.beds.destroy', [$space, $room, $bed]))
            ->assertRedirect();

        $this->assertSame(0, $room->beds()->count());
        $this->assertSame(0, $room->bedUnits()->count());

        foreach ($unitIds as $unitId) {
            $this->assertSoftDeleted(RoomBedUnit::class, ['id' => $unitId]);
        }
    }

    public function test_shared_space_can_publish_without_photos_when_space_and_rooms_skip_photos(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
            'name' => 'Hostal sin fotos por decision',
            'title' => 'Hostal sin fotos por decision',
            'short_description' => str_repeat('Descripcion corta compartida ', 5),
            'full_description' => str_repeat('Descripcion extendida compartida suficiente para validar. ', 7),
            'photos_skipped' => true,
            'room_photos_skipped' => true,
        ]);
        $room = $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion 101',
            'title' => 'Habitacion 101',
            'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
            'status' => 'active',
        ]);
        $bedType = BedType::where('slug', 'cama-matrimonial')->firstOrFail();
        $room->beds()->create([
            'company_id' => $user->company_id,
            'bed_type_id' => $bedType->id,
            'quantity' => 1,
            'capacity_per_bed' => $bedType->capacity,
            'total_capacity' => $bedType->capacity,
        ]);
        $space->generalServices()->sync([
            GeneralService::firstOrFail()->id => ['company_id' => $space->company_id],
        ]);
        $space->location()->create([
            'company_id' => $space->company_id,
            'country' => 'Bolivia',
            'city' => 'La Paz',
            'address' => 'Calle 1',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('spaces.shared.publish', $space))
            ->assertRedirect(route('spaces.shared.review', $space));

        $this->assertSame('completed', $space->refresh()->status);
    }

    public function test_room_photo_preference_applies_to_every_room(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $firstRoom, $secondRoom] = $this->sharedSpaceWithRooms($user);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.room-photos.settings', $space), [
                'room_photos_skipped' => true,
            ])
            ->assertRedirect(route('spaces.shared.photos.edit', $space));

        $this->assertTrue($space->refresh()->room_photos_skipped);
        $this->assertTrue($firstRoom->refresh()->photos_skipped);
        $this->assertTrue($secondRoom->refresh()->photos_skipped);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.room-photos.settings', $space))
            ->assertRedirect(route('spaces.shared.photos.edit', $space));

        $this->assertFalse($space->refresh()->room_photos_skipped);
        $this->assertFalse($firstRoom->refresh()->photos_skipped);
        $this->assertFalse($secondRoom->refresh()->photos_skipped);
    }

    public function test_shared_photo_page_has_one_global_room_photo_preference(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space] = $this->sharedSpaceWithRooms($user);

        $this
            ->actingAs($user)
            ->get(route('spaces.shared.photos.edit', $space))
            ->assertOk()
            ->assertSee('No usar fotos para las habitaciones')
            ->assertDontSee('No usar fotos para esta habitacion')
            ->assertSee(route('spaces.shared.room-photos.settings', $space), false);
    }

    public function test_shared_space_gallery_is_limited_to_three_extra_photos(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.photos.store', $space), [
                'gallery_photos' => collect(range(1, 4))
                    ->map(fn (int $index) => UploadedFile::fake()->image("general-{$index}.jpg"))
                    ->all(),
            ])
            ->assertSessionHasErrors('gallery_photos');
    }

    public function test_shared_room_gallery_is_limited_to_three_extra_photos(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);
        $room = $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion 101',
            'title' => 'Habitacion 101',
            'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.room-photos.store', [$space, $room]), [
                'gallery_photos' => collect(range(1, 4))
                    ->map(fn (int $index) => UploadedFile::fake()->image("room-{$index}.jpg"))
                    ->all(),
            ])
            ->assertSessionHasErrors('gallery_photos');
    }

    public function test_private_space_rejects_shared_room_creation(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.rooms.store', $space), [
                'name' => 'Habitacion no permitida',
                'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame(0, $space->rooms()->count());
    }

    public function test_company_user_can_delete_shared_space_and_room_photos(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);
        $room = $space->rooms()->create([
            'company_id' => $user->company_id,
            'title' => 'Habitacion 101',
            'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
            'status' => 'draft',
        ]);
        Storage::disk('public')->put('space-photo.webp', 'fake');
        Storage::disk('public')->put('room-photo.webp', 'fake');
        $spacePhoto = $space->photos()->create([
            'company_id' => $user->company_id,
            'path' => 'space-photo.webp',
            'type' => 'gallery',
        ]);
        $roomPhoto = $room->photos()->create([
            'company_id' => $user->company_id,
            'path' => 'room-photo.webp',
            'type' => 'gallery',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('spaces.shared.photos.destroy', [$space, $spacePhoto]))
            ->assertRedirect();

        $this
            ->actingAs($user)
            ->delete(route('spaces.shared.room-photos.destroy', [$space, $room, $roomPhoto]))
            ->assertRedirect();

        $this->assertDatabaseMissing('space_photos', ['id' => $spacePhoto->id]);
        $this->assertDatabaseMissing('room_photos', ['id' => $roomPhoto->id]);
        Storage::disk('public')->assertMissing('space-photo.webp');
        Storage::disk('public')->assertMissing('room-photo.webp');
    }

    public function test_shared_room_name_must_be_unique_inside_same_space(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);
        $bathroomType = BathroomType::where('slug', 'privado')->firstOrFail();

        $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion 101',
            'title' => 'Habitacion 101',
            'bathroom_type_id' => $bathroomType->id,
            'status' => 'active',
        ]);

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.rooms.store', $space), [
                'name' => 'Habitacion 101',
                'bathroom_type_id' => $bathroomType->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, $space->rooms()->count());
    }

    public function test_shared_stepper_room_store_returns_ajax_refresh_url(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);

        $this
            ->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('spaces.shared.rooms.store', $space), [
                'name' => 'Habitacion AJAX',
                'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
                'status' => 'active',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'refresh_url' => route('spaces.shared.rooms.edit', $space),
            ]);
    }

    public function test_company_user_can_reorder_shared_rooms(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);
        $bathroomType = BathroomType::where('slug', 'privado')->firstOrFail();
        $roomA = $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion A',
            'title' => 'Habitacion A',
            'bathroom_type_id' => $bathroomType->id,
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $roomB = $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion B',
            'title' => 'Habitacion B',
            'bathroom_type_id' => $bathroomType->id,
            'status' => 'active',
            'sort_order' => 2,
        ]);
        $roomC = $space->rooms()->create([
            'company_id' => $user->company_id,
            'name' => 'Habitacion C',
            'title' => 'Habitacion C',
            'bathroom_type_id' => $bathroomType->id,
            'status' => 'active',
            'sort_order' => 3,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(route('spaces.shared.rooms.order', $space), [
                'room_ids' => [$roomC->id, $roomA->id, $roomB->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame([1, 2, 3], [
            $roomC->refresh()->sort_order,
            $roomA->refresh()->sort_order,
            $roomB->refresh()->sort_order,
        ]);
        $this->assertSame(
            ['Habitacion C', 'Habitacion A', 'Habitacion B'],
            $space->refresh()->rooms()->pluck('name')->all(),
        );
    }

    public function test_company_user_can_copy_room_services_to_one_room_replacing_previous_services(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $sourceRoom, $targetRoom] = $this->sharedSpaceWithRooms($user);
        $services = RoomService::active()->limit(3)->get();
        $sourceRoom->roomServices()->sync([
            $services[0]->id => ['company_id' => $space->company_id],
            $services[1]->id => ['company_id' => $space->company_id],
        ]);
        $targetRoom->roomServices()->sync([
            $services[2]->id => ['company_id' => $space->company_id],
        ]);

        $this
            ->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('spaces.shared.room-services.copy', [$space, $sourceRoom]), [
                'target_room_ids' => [$targetRoom->id],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(
            $sourceRoom->roomServices()->pluck('room_services.id')->sort()->values()->all(),
            $targetRoom->roomServices()->pluck('room_services.id')->sort()->values()->all(),
        );
        $this->assertFalse($targetRoom->roomServices()->whereKey($services[2]->id)->exists());
    }

    public function test_company_user_can_copy_room_services_to_multiple_rooms(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $sourceRoom, $targetRoomA, $targetRoomB] = $this->sharedSpaceWithRooms($user, 3);
        $serviceIds = RoomService::active()->limit(2)->pluck('id');
        $sourceRoom->roomServices()->sync($serviceIds->mapWithKeys(fn (int $id): array => [$id => ['company_id' => $space->company_id]])->all());

        $this
            ->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('spaces.shared.room-services.copy', [$space, $sourceRoom]), [
                'target_room_ids' => [$targetRoomA->id, $targetRoomB->id],
            ])
            ->assertOk();

        $expected = $serviceIds->sort()->values()->all();
        $this->assertSame($expected, $targetRoomA->roomServices()->pluck('room_services.id')->sort()->values()->all());
        $this->assertSame($expected, $targetRoomB->roomServices()->pluck('room_services.id')->sort()->values()->all());
    }

    public function test_company_user_cannot_update_shared_room_with_future_blocking_reservation(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user);
        $this->futureReservationForRoom($space, $room);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.rooms.update', [$space, $room]), [
                'name' => 'Habitacion bloqueada',
                'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('room');

        $this->assertSame('Habitacion 1', $room->refresh()->name);
    }

    public function test_company_user_can_update_shared_room_from_rooms_step(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user, 1);
        $bathroomType = BathroomType::where('slug', 'compartido')->firstOrFail();

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.rooms.update', [$space, $room]), [
                'name' => 'Habitacion editada',
                'bathroom_type_id' => $bathroomType->id,
                'status' => 'inactive',
                'sale_mode' => 'flexible',
            ])
            ->assertRedirect(route('spaces.shared.rooms.edit', $space));

        $room->refresh();

        $this->assertSame('Habitacion editada', $room->name);
        $this->assertSame('Habitacion editada', $room->title);
        $this->assertSame($bathroomType->id, $room->bathroom_type_id);
        $this->assertSame('inactive', $room->status);
        $this->assertSame('flexible', $room->sale_mode);
    }

    public function test_company_user_cannot_update_shared_room_reserved_through_multi_room_detail(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user);
        $reservation = $this->futureReservationForRoom($space, null);
        ReservationRoom::query()->create([
            'reservation_id' => $reservation->id,
            'space_room_id' => $room->id,
            'occupancy_block_id' => null,
            'capacity' => 2,
            'price_per_night' => 100,
            'subtotal_amount' => 200,
        ]);

        $this
            ->actingAs($user)
            ->post(route('spaces.shared.beds.store', [$space, $room]), [
                'bed_type_id' => BedType::where('slug', 'cama-matrimonial')->firstOrFail()->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors('room');

        $this->assertSame(0, $room->beds()->count());
    }

    public function test_company_user_can_update_shared_room_when_reservation_is_past_or_cancelled(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $room] = $this->sharedSpaceWithRooms($user);
        $this->futureReservationForRoom($space, $room, ['status' => 'cancelled']);
        $this->pastReservationForRoom($space, $room);

        $this
            ->actingAs($user)
            ->put(route('spaces.shared.rooms.update', [$space, $room]), [
                'name' => 'Habitacion editable',
                'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertSame('Habitacion editable', $room->refresh()->name);
    }

    public function test_copy_room_services_rejects_room_from_other_space(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $sourceRoom] = $this->sharedSpaceWithRooms($user);
        [$otherSpace, $otherRoom] = $this->sharedSpaceWithRooms($user);

        $this
            ->actingAs($user)
            ->postJson(route('spaces.shared.room-services.copy', [$space, $sourceRoom]), [
                'target_room_ids' => [$otherRoom->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_room_ids');

        $this->assertNotSame($space->id, $otherSpace->id);
    }

    public function test_copy_room_services_rejects_same_source_room_as_target(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $user = $this->companyUserWithSpacePermission();
        [$space, $sourceRoom] = $this->sharedSpaceWithRooms($user);

        $this
            ->actingAs($user)
            ->postJson(route('spaces.shared.room-services.copy', [$space, $sourceRoom]), [
                'target_room_ids' => [$sourceRoom->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('target_room_ids');
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

    private function sharedSpaceWithRooms(User $user, int $roomCount = 2): array
    {
        $space = Space::factory()->create([
            'company_id' => $user->company_id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hostal')->firstOrFail()->id,
            'private_space_type_id' => null,
        ]);
        $bathroomType = BathroomType::where('slug', 'privado')->firstOrFail();
        $rooms = collect(range(1, $roomCount))
            ->map(fn (int $index): SpaceRoom => $space->rooms()->create([
                'company_id' => $user->company_id,
                'name' => "Habitacion {$index}",
                'title' => "Habitacion {$index}",
                'bathroom_type_id' => $bathroomType->id,
                'status' => 'active',
                'sort_order' => $index,
            ]));

        return [$space, ...$rooms->all()];
    }

    private function futureReservationForRoom(Space $space, ?SpaceRoom $room, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'company_id' => $space->company_id,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(9)->toDateString(),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            ...$attributes,
        ]);
    }

    private function pastReservationForRoom(Space $space, SpaceRoom $room, array $attributes = []): Reservation
    {
        return Reservation::factory()->create([
            'company_id' => $space->company_id,
            'space_id' => $space->id,
            'space_room_id' => $room->id,
            'check_in' => now()->subDays(9)->toDateString(),
            'check_out' => now()->subDays(7)->toDateString(),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            ...$attributes,
        ]);
    }
}
