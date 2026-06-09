<?php

namespace Tests\Feature\PublicAccommodation;

use App\Models\AvailabilityDay;
use App\Models\AccommodationPackage;
use App\Models\Company;
use App\Models\OccupancyBlock;
use App\Models\PackageService;
use App\Models\PrivateSpaceType;
use App\Models\Reservation;
use App\Models\RoomBed;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceLocation;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccommodationSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_public_search_page(): void
    {
        $this->get(route('public.accommodations.index'))
            ->assertOk()
            ->assertSee('Encuentra tu proxima estadia')
            ->assertSee('Destino');
    }

    public function test_search_shows_only_available_private_spaces_for_dates_and_guests(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $available = $this->privateSpace($company, ['title' => 'Casa Clara', 'max_capacity' => 4]);
        $blocked = $this->privateSpace($company, ['title' => 'Casa Bloqueada', 'max_capacity' => 4]);
        $closed = $this->privateSpace($company, ['title' => 'Casa Cerrada', 'max_capacity' => 4]);

        $this->openPrivate($available, '2026-07-01', '2026-07-02');
        $this->openPrivate($blocked, '2026-07-01', '2026-07-02');
        $this->openPrivate($closed, '2026-07-01', '2026-07-02', 'closed');
        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $blocked->id,
            'space_room_id' => null,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
        ]);

        $this->get(route('public.accommodations.search', [
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Casa Clara')
            ->assertDontSee('Casa Bloqueada')
            ->assertDontSee('Casa Cerrada')
            ->assertSee('Casa');
    }

    public function test_shared_space_is_listed_when_rooms_cover_guest_capacity(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $room] = $this->sharedSpace($company);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        $this->openRoom($space, $room, '2026-07-01', '2026-07-02');

        $this->get(route('public.accommodations.search', [
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Hostal Centro')
            ->assertSee('Hotel')
            ->assertSee('1 habitacion');
    }

    public function test_search_by_destination_coordinates_shows_spaces_within_twenty_five_kilometers(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $near = $this->privateSpace($company, ['title' => 'Casa Cerca'], [
            'latitude' => '-16.5000000',
            'longitude' => '-68.1500000',
        ]);
        $far = $this->privateSpace($company, ['title' => 'Casa Lejana'], [
            'city' => 'Santa Cruz de la Sierra',
            'state_or_region' => 'Santa Cruz',
            'latitude' => '-17.7833000',
            'longitude' => '-63.1833000',
        ]);

        $this->get(route('public.accommodations.search', [
            'destination' => 'La Paz, Bolivia',
            'destination_latitude' => '-16.5000000',
            'destination_longitude' => '-68.1500000',
            'destination_city' => 'La Paz',
            'destination_state' => 'La Paz',
            'destination_country' => 'Bolivia',
            'guests' => 1,
        ]))
            ->assertOk()
            ->assertSee($near->title)
            ->assertDontSee($far->title);
    }

    public function test_coordinate_search_orders_nearby_spaces_by_distance(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $farther = $this->privateSpace($company, ['title' => 'Casa Alfa'], [
            'latitude' => '-16.5700000',
            'longitude' => '-68.1800000',
        ]);
        $nearest = $this->privateSpace($company, ['title' => 'Casa Zeta'], [
            'latitude' => '-16.5000000',
            'longitude' => '-68.1500000',
        ]);

        $this->get(route('public.accommodations.search', [
            'destination' => 'La Paz, Bolivia',
            'destination_latitude' => '-16.5000000',
            'destination_longitude' => '-68.1500000',
            'destination_city' => 'La Paz',
            'destination_state' => 'La Paz',
            'destination_country' => 'Bolivia',
            'guests' => 1,
        ]))
            ->assertOk()
            ->assertSeeInOrder([$nearest->title, $farther->title]);
    }

    public function test_search_by_destination_components_works_without_coordinates(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $matching = $this->privateSpace($company, ['title' => 'Casa Departamento'], [
            'city' => 'El Alto',
            'state_or_region' => 'La Paz',
            'latitude' => null,
            'longitude' => null,
        ]);
        $other = $this->privateSpace($company, ['title' => 'Casa Otro Departamento'], [
            'city' => 'Cochabamba',
            'state_or_region' => 'Cochabamba',
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->get(route('public.accommodations.search', [
            'destination' => 'Departamento de La Paz',
            'destination_state' => 'Departamento de La Paz',
            'destination_country' => 'Bolivia',
            'guests' => 1,
        ]))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);
    }

    public function test_manual_destination_text_search_still_works_without_google_fields(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $matching = $this->privateSpace($company, ['title' => 'Casa Manual'], [
            'zone_or_neighborhood' => 'Sopocachi',
        ]);
        $other = $this->privateSpace($company, ['title' => 'Casa Fuera'], [
            'zone_or_neighborhood' => 'Calacoto',
        ]);

        $this->get(route('public.accommodations.search', [
            'destination' => 'Sopocachi',
            'guests' => 1,
        ]))
            ->assertOk()
            ->assertSee($matching->title)
            ->assertDontSee($other->title);
    }

    public function test_checkout_must_be_after_checkin(): void
    {
        $this->get(route('public.accommodations.search', [
            'check_in' => '2026-07-03',
            'check_out' => '2026-07-01',
            'guests' => 2,
        ]))
            ->assertRedirect()
            ->assertSessionHasErrors('check_out');
    }

    public function test_detail_page_links_to_reservation_flow(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Detalle', 'short_description' => 'Cerca del centro']);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Casa Detalle')
            ->assertSee('Reservar con adelanto')
            ->assertSee('Cerca del centro')
            ->assertSee('Precio del espacio')
            ->assertSee('Precio por noche')
            ->assertDontSee('Precio por persona');
    }

    public function test_private_detail_page_shows_commercial_package_presentation_before_reservation(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Paquete Comercial']);
        $package = $this->packageFor($company, $space, [
            'name' => 'Escapada Romantica',
            'badges' => ['Ideal para pareja', 'Experiencia romantica', 'Todo incluido'],
            'price_display_text' => '990 Bs la pareja',
            'conditions' => 'Valido fines de semana segun disponibilidad.',
        ]);
        $service = PackageService::query()->create([
            'company_id' => $company->id,
            'name' => 'Cena especial',
            'type' => 'alimentacion',
            'icon' => 'tools-kitchen-2',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $package->services()->attach($service->id, [
            'inclusion_type' => 'included',
            'sort_order' => 1,
        ]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Paquetes disponibles')
            ->assertSee('Escapada Romantica')
            ->assertSee('Ideal para pareja')
            ->assertSee('Experiencia romantica')
            ->assertSee('Todo incluido')
            ->assertSee('990 Bs la pareja')
            ->assertSee('Cena especial')
            ->assertSee('Valido fines de semana segun disponibilidad.')
            ->assertSee('La reserva se confirma despues de validar el adelanto por QR')
            ->assertDontSee('Acceso para guardar tu reserva');
    }

    public function test_guest_can_create_pending_payment_private_reservation_with_price_per_space(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Reserva', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertAuthenticated();
        $this->assertSame('pending_payment', $reservation->status);
        $this->assertSame('pending', $reservation->payment_status);
        $this->assertSame(2, $reservation->nights);
        $this->assertSame(2, $reservation->guests);
        $this->assertSame('120.00', $reservation->price_per_person);
        $this->assertSame('240.00', $reservation->total_amount);
        $this->assertSame('120.00', $reservation->advance_amount);
        $this->assertSame('120.00', $reservation->balance_amount);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'type' => 'unavailable',
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
    }

    public function test_guest_can_create_pending_payment_shared_reservation_with_price_per_room(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $room] = $this->sharedSpace($company);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        $this->openRoom($space, $room, '2026-07-01', '2026-07-02');

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'space_room_id' => $room->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-shared@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertAuthenticated();
        $this->assertSame('pending_payment', $reservation->status);
        $this->assertSame('pending', $reservation->payment_status);
        $this->assertSame(2, $reservation->nights);
        $this->assertSame(2, $reservation->guests);
        $this->assertSame('80.00', $reservation->price_per_person);
        $this->assertSame('160.00', $reservation->total_amount);
        $this->assertSame('80.00', $reservation->advance_amount);
        $this->assertSame('80.00', $reservation->balance_amount);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $room->id,
            'type' => 'unavailable',
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
        $this->assertDatabaseHas('reservation_rooms', [
            'reservation_id' => $reservation->id,
            'space_room_id' => $room->id,
            'capacity' => 2,
            'price_per_night' => '80.00',
            'subtotal_amount' => '160.00',
        ]);
    }

    public function test_guest_can_create_one_shared_reservation_with_multiple_rooms(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $roomA] = $this->sharedSpace($company);
        $roomB = $this->sharedRoom($company, $space, 'Habitacion 2', 3);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomA->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomB->id,
            'quantity' => 3,
            'capacity_per_bed' => 1,
            'total_capacity' => 3,
        ]);
        $this->openRoom($space, $roomA, '2026-07-01', '2026-07-02', 80);
        $this->openRoom($space, $roomB, '2026-07-01', '2026-07-02', 120);

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'space_room_ids' => [$roomA->id, $roomB->id],
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 4,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-multi@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertSame('pending_payment', $reservation->status);
        $this->assertNull($reservation->space_room_id);
        $this->assertSame(2, $reservation->nights);
        $this->assertSame(4, $reservation->guests);
        $this->assertSame('200.00', $reservation->price_per_person);
        $this->assertSame('400.00', $reservation->total_amount);
        $this->assertSame('200.00', $reservation->advance_amount);
        $this->assertSame('200.00', $reservation->balance_amount);
        $this->assertDatabaseCount('reservations', 1);
        $this->assertDatabaseHas('reservation_rooms', [
            'reservation_id' => $reservation->id,
            'space_room_id' => $roomA->id,
            'capacity' => 2,
            'price_per_night' => '80.00',
            'subtotal_amount' => '160.00',
        ]);
        $this->assertDatabaseHas('reservation_rooms', [
            'reservation_id' => $reservation->id,
            'space_room_id' => $roomB->id,
            'capacity' => 3,
            'price_per_night' => '120.00',
            'subtotal_amount' => '240.00',
        ]);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $roomA->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $roomB->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
    }

    public function test_shared_reservation_requires_selected_rooms_to_cover_guest_capacity(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $room] = $this->sharedSpace($company);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        $this->openRoom($space, $room, '2026-07-01', '2026-07-02');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'space_room_ids' => [$room->id],
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 4,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-capacity@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('guests');
    }

    public function test_shared_reservation_fails_when_one_selected_room_is_not_available(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $roomA] = $this->sharedSpace($company);
        $roomB = $this->sharedRoom($company, $space, 'Habitacion 2', 2);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomA->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomB->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        $this->openRoom($space, $roomA, '2026-07-01', '2026-07-02', 80);
        $this->openRoom($space, $roomB, '2026-07-01', '2026-07-02', 120, 'closed');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'space_room_ids' => [$roomA->id, $roomB->id],
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 4,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-closed@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('space_room_ids');
    }

    public function test_shared_reservation_fails_when_one_selected_room_is_blocked(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $roomA] = $this->sharedSpace($company);
        $roomB = $this->sharedRoom($company, $space, 'Habitacion 2', 2);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomA->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomB->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);
        $this->openRoom($space, $roomA, '2026-07-01', '2026-07-02', 80);
        $this->openRoom($space, $roomB, '2026-07-01', '2026-07-02', 120);
        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $roomB->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'space_room_ids' => [$roomA->id, $roomB->id],
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 4,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-blocked@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('space_room_ids');
    }

    public function test_guest_can_create_package_reservation_with_fixed_price_and_extra_people(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Paquete', 'max_capacity' => 5]);
        $package = $this->packageFor($company, $space, [
            'name' => 'Escapada Familiar',
            'price' => 500,
            'included_people' => 2,
            'max_people' => 5,
            'extra_person_price' => 90,
            'nights_included' => 2,
        ]);
        $service = PackageService::query()->create([
            'company_id' => $company->id,
            'name' => 'Desayuno incluido',
            'type' => 'alimentacion',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $package->services()->attach($service->id, [
            'inclusion_type' => 'included',
            'sort_order' => 1,
        ]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 4,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-package@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertSame('package', $reservation->booking_type);
        $this->assertSame($package->id, $reservation->package_id);
        $this->assertSame('500.00', $reservation->package_price);
        $this->assertSame(2, $reservation->included_people);
        $this->assertSame(2, $reservation->extra_people);
        $this->assertSame('180.00', $reservation->extra_people_total);
        $this->assertSame(0, $reservation->package_extra_nights);
        $this->assertSame('0.00', $reservation->package_extra_nights_total);
        $this->assertSame('680.00', $reservation->total_amount);
        $this->assertSame('340.00', $reservation->advance_amount);
        $this->assertSame('340.00', $reservation->deposit_amount);
        $this->assertSame('340.00', $reservation->balance_amount);
        $this->assertSame('Escapada Familiar', $reservation->package_snapshot['name']);
        $this->assertSame('Desayuno incluido', $reservation->package_snapshot['services'][0]['name']);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
    }

    public function test_package_reservation_rejects_guests_above_package_maximum(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Maximo', 'max_capacity' => 6]);
        $package = $this->packageFor($company, $space, [
            'included_people' => 2,
            'max_people' => 4,
            'extra_person_price' => 80,
        ]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 5,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-package-max@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('guests');
    }

    private function privateSpace(Company $company, array $attributes = [], array $locationAttributes = []): Space
    {
        $space = Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'shared_space_type_id' => null,
            'title' => 'Casa Vista',
            'name' => 'Casa Vista',
            'max_capacity' => 4,
            'status' => 'active',
            ...$attributes,
        ]);

        SpaceLocation::query()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'country' => 'Bolivia',
            'state_or_region' => 'La Paz',
            'city' => 'La Paz',
            'zone_or_neighborhood' => 'Centro',
            'address' => 'Calle 1',
            ...$locationAttributes,
        ]);

        return $space;
    }

    private function sharedSpace(Company $company): array
    {
        $space = Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'private_space_type_id' => null,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hotel')->firstOrFail()->id,
            'title' => null,
            'name' => 'Hostal Centro',
            'max_capacity' => 2,
            'status' => 'active',
        ]);
        $room = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'name' => 'Habitacion 1',
            'title' => 'Habitacion 1',
            'max_capacity' => 2,
            'status' => 'active',
        ]);

        SpaceLocation::query()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'country' => 'Bolivia',
            'state_or_region' => 'La Paz',
            'city' => 'La Paz',
            'zone_or_neighborhood' => 'Centro',
            'address' => 'Calle 2',
        ]);

        return [$space, $room];
    }

    private function openPrivate(Space $space, string $start, string $end, string $status = 'available'): void
    {
        foreach ([$start, $end] as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $space->company_id,
                'space_id' => $space->id,
                'space_room_id' => null,
                'date' => $date,
                'price' => 120,
                'status' => $status,
            ]);
        }
    }

    private function packageFor(Company $company, Space $space, array $attributes = []): AccommodationPackage
    {
        $package = AccommodationPackage::query()->create([
            'company_id' => $company->id,
            'name' => 'Paquete Romantico',
            'slug' => 'paquete-romantico',
            'short_description' => 'Todo incluido para reservar el espacio completo.',
            'commercial_description' => 'Incluye alojamiento y servicios seleccionados.',
            'conditions' => 'Sujeto a disponibilidad.',
            'price' => 500,
            'currency' => 'BOB',
            'included_people' => 2,
            'max_people' => 4,
            'extra_person_price' => 80,
            'requires_full_private_space' => true,
            'nights_included' => 2,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 1,
            ...$attributes,
        ]);

        $package->spaces()->attach($space->id);

        return $package;
    }

    private function sharedRoom(Company $company, Space $space, string $name, int $capacity): SpaceRoom
    {
        return SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'name' => $name,
            'title' => $name,
            'max_capacity' => $capacity,
            'status' => 'active',
        ]);
    }

    private function openRoom(Space $space, SpaceRoom $room, string $start, string $end, int $price = 80, string $status = 'available'): void
    {
        foreach ([$start, $end] as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $space->company_id,
                'space_id' => $space->id,
                'space_room_id' => $room->id,
                'date' => $date,
                'price' => $price,
                'status' => $status,
            ]);
        }
    }
}
