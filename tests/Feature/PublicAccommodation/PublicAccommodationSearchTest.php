<?php

namespace Tests\Feature\PublicAccommodation;

use App\Models\AvailabilityDay;
use App\Models\AccommodationPackage;
use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\Company;
use App\Models\OccupancyBlock;
use App\Models\PackageService;
use App\Models\PrivateSpaceType;
use App\Models\Reservation;
use App\Models\ReservationChannel;
use App\Models\ReservationGroup;
use App\Models\RoomBed;
use App\Models\RoomBedUnit;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceLocation;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use App\Models\User;
use App\Services\PublicSite\PublicReservationService;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
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

    public function test_offline_space_is_hidden_from_public_search_detail_and_reservation(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, [
            'title' => 'Casa Interna',
            'max_capacity' => 4,
            'is_public_online' => false,
        ]);

        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->get(route('public.accommodations.search', [
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertDontSee('Casa Interna');

        $this->get(route('public.accommodations.show', [
            'space' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))->assertNotFound();

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-offline@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('space_id');
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

    public function test_private_detail_page_shows_availability_calendar_modal(): void
    {
        $this->travelTo('2026-06-15 08:00:00');
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Calendario']);
        $this->openPrivate($space, '2026-07-01', '2026-07-01');
        $this->openPrivate($space, '2026-07-02', '2026-07-02', 'closed');

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Ver calendario disponible')
            ->assertSee('Calendario de disponibilidad')
            ->assertSee('Disponibilidad del espacio')
            ->assertSee('Disponible')
            ->assertSee('No disponible')
            ->assertSee('data-public-calendar-date="2026-07-01"', false)
            ->assertSee('data-public-calendar-apply', false)
            ->assertSee('01/07/2026')
            ->assertSee('02/07/2026');
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

    public function test_private_detail_page_shows_unavailable_dates_message_in_packages_section(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Sin Fechas', 'max_capacity' => 4]);
        $this->packageFor($company, $space, ['name' => 'Paquete Bloqueado']);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');
        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-01',
        ]);

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Paquetes disponibles')
            ->assertSee('Fechas no disponibles')
            ->assertSee('Fechas disponibles')
            ->assertSee('02/07/2026')
            ->assertSee('Disponible')
            ->assertSee('Fechas ocupadas')
            ->assertSee('01/07/2026')
            ->assertSee('Ocupada')
            ->assertDontSee('Paquete Bloqueado')
            ->assertDontSee('Reservar este paquete');
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
        $this->assertNotNull($reservation->reservation_group_id);
        $this->assertSame('pending_payment', $reservation->status);
        $this->assertSame('pending', $reservation->payment_status);
        $this->assertSame(2, $reservation->nights);
        $this->assertSame(2, $reservation->guests);
        $this->assertSame('120.00', $reservation->price_per_person);
        $this->assertSame('240.00', $reservation->total_amount);
        $this->assertSame('120.00', $reservation->advance_amount);
        $this->assertSame('120.00', $reservation->balance_amount);

        $channel = ReservationChannel::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('slug', 'pagina-web')
            ->firstOrFail();

        $this->assertSame('Página web', $channel->name);
        $this->assertSame($channel->id, $reservation->reservation_channel_id);

        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'type' => 'unavailable',
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);

        $group = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->firstOrFail();
        $statement = AccountStatement::query()
            ->withoutGlobalScope('company')
            ->where('reservation_group_id', $group->id)
            ->firstOrFail();

        $this->assertSame($group->id, $reservation->reservation_group_id);
        $this->assertSame($channel->id, $group->reservation_channel_id);
        $this->assertSame('Ana Perez', $group->guest_name);
        $this->assertSame('pending_payment', $group->status);
        $this->assertSame('pending', $group->payment_status);
        $this->assertSame('240.00', $group->total_amount);
        $this->assertSame('120.00', $group->advance_amount);
        $this->assertSame('120.00', $group->balance_amount);
        $this->assertSame(1, Reservation::query()->withoutGlobalScope('company')->where('reservation_group_id', $group->id)->count());
        $this->assertSame('240.00', $statement->subtotal);
        $this->assertSame(2, AccountStatementItem::query()->withoutGlobalScope('company')->where('account_statement_id', $statement->id)->count());
    }

    public function test_public_reservation_can_continue_to_internal_check_in_flow(): void
    {
        $this->travelTo('2026-07-01 08:00:00');
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $staff = User::factory()->create([
            'company_id' => $company->id,
            'is_active' => true,
        ]);
        Permission::findOrCreate('reservations.manage', 'web');
        Permission::findOrCreate('occupancy.manage', 'web');
        $staff->givePermissionTo(['reservations.manage', 'occupancy.manage']);
        $space = $this->privateSpace($company, ['title' => 'Casa Check In Publico', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-checkin-public@example.com',
            'guest_phone' => '76543210',
            'guest_document' => '123456',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $group = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->with('reservations.occupancyBlock')
            ->firstOrFail();

        $this->actingAs($staff)
            ->patch(route('admin.reservation-groups.confirm', $group))
            ->assertRedirect(route('admin.reservation-groups.show', $group));

        $this->actingAs($staff)
            ->post(route('admin.reservation-groups.check-in', $group))
            ->assertRedirect(route('check-ins.create', ['reservation_group_id' => $group->id]));

        $this->actingAs($staff)
            ->get(route('check-ins.create', ['reservation_group_id' => $group->id]))
            ->assertOk()
            ->assertSee('value="123456"', false)
            ->assertSee('value="Ana"', false)
            ->assertSee('value="Perez"', false)
            ->assertSee('value="2"', false)
            ->assertSee('value="'.$space->id.'"', false)
            ->assertSee('value="120.00"', false);
    }

    public function test_public_payment_proof_updates_internal_reservation_group_status(): void
    {
        Storage::fake('public');
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Comprobante', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-proof@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $this->post(route('public.reservations.payment-proof', $reservation), [
            'payment_reference' => 'QR-123',
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
        ])->assertRedirect(route('public.reservations.show', $reservation->id));

        $reservation->refresh();
        $group = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->whereKey($reservation->reservation_group_id)
            ->firstOrFail();

        $this->assertSame('payment_under_review', $reservation->status);
        $this->assertSame('submitted', $reservation->payment_status);
        $this->assertSame('payment_under_review', $group->status);
        $this->assertSame('pending', $group->payment_status);
        $this->assertSame('QR-123', $group->payment_reference);
    }

    public function test_public_reservation_uses_company_advance_percentage(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create([
            'reservation_advance_percentage' => 30,
        ]);
        $space = $this->privateSpace($company, ['title' => 'Casa Adelanto', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-advance@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertSame('240.00', $reservation->total_amount);
        $this->assertSame('72.00', $reservation->advance_amount);
        $this->assertSame('168.00', $reservation->balance_amount);
    }

    public function test_private_quote_endpoint_returns_room_only_quote_without_reload(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Ajax', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->getJson(route('public.accommodations.quote', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('quote.booking_type', 'normal')
            ->assertJsonPath('quote.mode_label', 'Solo habitacion')
            ->assertJsonPath('quote.title', 'Total solo habitacion')
            ->assertJsonPath('quote.total', '240.00 Bs')
            ->assertJsonPath('quote.button_label', 'Reservar solo habitacion')
            ->assertJsonPath('availability.is_available', true)
            ->assertJsonPath('availability.badge', 'Fechas disponibles');
    }

    public function test_private_quote_endpoint_returns_unavailable_payload_without_reload(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Ajax Ocupada', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-01');
        $this->openPrivate($space, '2026-07-02', '2026-07-02', 'closed');

        $this->getJson(route('public.accommodations.quote', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('availability.is_available', false)
            ->assertJsonPath('availability.badge', 'Fechas no disponibles')
            ->assertJsonCount(1, 'availability.available_dates')
            ->assertJsonCount(1, 'availability.unavailable_dates')
            ->assertJsonPath('availability.available_dates.0.date', '2026-07-01')
            ->assertJsonPath('availability.unavailable_dates.0.date', '2026-07-02');
    }

    public function test_private_quote_endpoint_returns_package_quote_without_reload(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Paquete Ajax', 'max_capacity' => 4]);
        $package = $this->packageFor($company, $space, [
            'price' => 500,
            'included_people' => 2,
            'max_people' => 4,
            'extra_person_price' => 80,
        ]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $response = $this->getJson(route('public.accommodations.quote', [
            'space' => $space,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 3,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('quote.booking_type', 'package')
            ->assertJsonPath('quote.mode_label', 'Paquete')
            ->assertJsonPath('quote.title', 'Total final del paquete')
            ->assertJsonPath('quote.total', '660.00 Bs')
            ->assertJsonPath('quote.lines.4.value', '1 x 2 noches = 160.00 Bs')
            ->assertJsonPath('quote.lines.5.label', '2026-07-01')
            ->assertJsonPath('quote.lines.5.value', '1 x 80.00 Bs = 80.00 Bs')
            ->assertJsonPath('quote.lines.6.label', '2026-07-02')
            ->assertJsonPath('quote.lines.6.value', '1 x 80.00 Bs = 80.00 Bs')
            ->assertJsonPath('quote.button_label', 'Reservar este paquete')
            ->assertJsonPath('availability.is_available', true)
            ->assertJsonPath('availability.badge', 'Fechas disponibles');

        parse_str(parse_url((string) $response->json('quote.reservation_url'), PHP_URL_QUERY), $query);

        $this->assertSame((string) $space->id, $query['space_id']);
        $this->assertSame((string) $package->id, $query['package_id']);
        $this->assertSame('2026-07-01', $query['check_in']);
        $this->assertSame('2026-07-03', $query['check_out']);
        $this->assertSame('3', $query['guests']);
    }

    public function test_public_reservation_rejects_dates_marked_offline_from_availability_grid(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Offline Dia', 'max_capacity' => 4]);
        $this->openPrivate($space, '2026-07-01', '2026-07-01', 'available', true);
        $this->openPrivate($space, '2026-07-02', '2026-07-02', 'available', false);

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('No disponible para las fechas consultadas.');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-offline-day@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('check_in');
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

    public function test_shared_bed_unit_public_reservation_uses_bed_unit_price(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        [$space, $room] = $this->sharedSpace($company);
        $room->update(['sale_mode' => 'bed_unit']);
        $bed = RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'quantity' => 1,
            'capacity_per_bed' => 1,
            'total_capacity' => 1,
        ]);
        $bedUnit = RoomBedUnit::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'room_bed_id' => $bed->id,
            'label' => 'Cama 1',
            'sort_order' => 1,
        ]);

        $this->openRoom($space, $room, '2026-07-01', '2026-07-02', 200);
        $this->openBedUnit($space, $room, $bedUnit, '2026-07-01', '2026-07-02', 70);

        $this->get(route('public.accommodations.show', [
            'space' => $space->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 1,
        ]))
            ->assertOk()
            ->assertSee('70.00 Bs/noche')
            ->assertDontSee('100.00 Bs/noche');

        $response = $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'room_bed_unit_ids' => [$bedUnit->id],
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 1,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-bed@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasNoErrors();
        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();

        $response->assertRedirect(route('public.reservations.show', $reservation->id));
        $this->assertSame('70.00', $reservation->price_per_person);
        $this->assertSame('140.00', $reservation->total_amount);
        $this->assertDatabaseHas('reservation_bed_units', [
            'reservation_id' => $reservation->id,
            'room_bed_unit_id' => $bedUnit->id,
            'price_per_night' => '70.00',
            'subtotal_amount' => '140.00',
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
        $this->assertSame('360.00', $reservation->extra_people_total);
        $this->assertSame(0, $reservation->package_extra_nights);
        $this->assertSame('0.00', $reservation->package_extra_nights_total);
        $this->assertSame('860.00', $reservation->total_amount);
        $this->assertSame('430.00', $reservation->advance_amount);
        $this->assertSame('430.00', $reservation->deposit_amount);
        $this->assertSame('430.00', $reservation->balance_amount);
        $this->assertSame('Escapada Familiar', $reservation->package_snapshot['name']);
        $this->assertSame('Desayuno incluido', $reservation->package_snapshot['services'][0]['name']);
        $extraItems = AccountStatementItem::query()
            ->withoutGlobalScope('company')
            ->where('reservation_id', $reservation->id)
            ->where('type', 'extra')
            ->orderBy('date')
            ->get();
        $this->assertCount(2, $extraItems);
        $this->assertSame(['2026-07-01', '2026-07-02'], $extraItems->pluck('date')->map->toDateString()->all());
        $this->assertSame(['2.00', '2.00'], $extraItems->pluck('quantity')->all());
        $this->assertSame(['90.00', '90.00'], $extraItems->pluck('unit_price')->all());
        $this->assertSame(['180.00', '180.00'], $extraItems->pluck('total')->all());
        $this->assertStringContainsString('Personas extra paquete Escapada Familiar - Noche 2026-07-01', $extraItems[0]->description);
        $this->assertDatabaseHas('occupancy_blocks', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-02',
        ]);
    }

    public function test_package_reservation_adds_extra_nights_to_total_amount(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Paquete Largo', 'max_capacity' => 4]);
        $package = $this->packageFor($company, $space, [
            'name' => 'Paquete B',
            'price' => 2000,
            'included_people' => 2,
            'max_people' => 4,
            'extra_person_price' => 90,
            'nights_included' => 2,
        ]);
        foreach (['2026-07-01', '2026-07-02', '2026-07-03'] as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $space->company_id,
                'space_id' => $space->id,
                'space_room_id' => null,
                'date' => $date,
                'price' => 400,
                'status' => 'available',
            ]);
        }

        $quote = app(PublicReservationService::class)->quote([
            'space_id' => $space->id,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-04',
            'guests' => 2,
        ]);

        $this->assertSame(1, $quote['package_extra_nights']);
        $this->assertSame(400.0, $quote['package_extra_nights_total']);
        $this->assertSame(2400.0, $quote['total_amount']);
        $this->assertSame(1200.0, $quote['advance_amount']);
        $this->assertSame(1200.0, $quote['balance_amount']);

        $this->get(route('public.accommodations.show', [
            'space' => $space,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-04',
            'guests' => 2,
        ]))
            ->assertOk()
            ->assertSee('Total final del paquete')
            ->assertSee('Noches extra')
            ->assertSee('2400.00 Bs');

        $this->post(route('public.reservations.store'), [
            'space_id' => $space->id,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-04',
            'guests' => 2,
            'guest_name' => 'Ana Perez',
            'guest_email' => 'ana-package-long@example.com',
            'guest_phone' => '76543210',
            'account_mode' => 'register',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $reservation = Reservation::query()->withoutGlobalScope('company')->firstOrFail();
        $items = AccountStatementItem::query()
            ->withoutGlobalScope('company')
            ->where('reservation_id', $reservation->id)
            ->where('type', 'lodging_night')
            ->orderBy('date')
            ->get();

        $this->assertSame(['1000.00', '1000.00', '400.00'], $items->pluck('unit_price')->all());
        $this->assertStringContainsString('Detalle paquete Paquete B', $items[0]->description);
        $this->assertStringContainsString('Detalle paquete Paquete B', $items[1]->description);
        $this->assertStringContainsString('Hospedaje extra', $items[2]->description);
    }

    public function test_package_quote_allows_extra_people_above_package_maximum_when_extra_price_exists(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        $company = Company::factory()->create();
        $space = $this->privateSpace($company, ['title' => 'Casa Maximo', 'max_capacity' => 6]);
        $package = $this->packageFor($company, $space, [
            'included_people' => 2,
            'max_people' => 2,
            'extra_person_price' => 80,
        ]);
        $this->openPrivate($space, '2026-07-01', '2026-07-02');

        $this->getJson(route('public.accommodations.quote', [
            'space' => $space,
            'package_id' => $package->id,
            'check_in' => '2026-07-01',
            'check_out' => '2026-07-03',
            'guests' => 3,
        ]))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('quote.booking_type', 'package')
            ->assertJsonPath('quote.total', '660.00 Bs')
            ->assertJsonPath('quote.button_label', 'Reservar este paquete');
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

    private function openPrivate(Space $space, string $start, string $end, string $status = 'available', bool $isPublicOnline = true): void
    {
        foreach (collect([$start, $end])->unique() as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $space->company_id,
                'space_id' => $space->id,
                'space_room_id' => null,
                'date' => $date,
                'price' => 120,
                'status' => $status,
                'is_public_online' => $isPublicOnline,
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

    private function openBedUnit(Space $space, SpaceRoom $room, RoomBedUnit $bedUnit, string $start, string $end, int $price = 80, string $status = 'available'): void
    {
        foreach ([$start, $end] as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $space->company_id,
                'space_id' => $space->id,
                'space_room_id' => $room->id,
                'room_bed_unit_id' => $bedUnit->id,
                'date' => $date,
                'price' => $price,
                'status' => $status,
            ]);
        }
    }
}
