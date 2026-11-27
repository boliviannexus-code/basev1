<?php

namespace Tests\Feature;

use App\Models\BedType;
use App\Models\Company;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\OccupancyBlock;
use App\Models\OnlineCheckInSubmission;
use App\Models\PrivateSpaceType;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\RoomBed;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OnlineCheckInTest extends TestCase
{
    use RefreshDatabase;

    public function test_welcome_is_public_company_specific_and_translated(): void
    {
        $company = Company::factory()->create(['name' => 'Hotel Andino', 'public_slug' => 'hotel-andino']);
        $other = Company::factory()->create(['name' => 'Hotel Sur', 'public_slug' => 'hotel-sur']);

        $this->get(route('public.online-check-in.welcome', $company))->assertOk()
            ->assertSee('Hotel Andino')->assertSee('¡Bienvenido!')->assertSee('Tengo una reserva')->assertSee('No tengo una reserva')->assertDontSee('Hotel Sur');
        $this->get(route('public.online-check-in.welcome', ['company' => $other, 'lang' => 'en']))->assertOk()
            ->assertSee('Hotel Sur')->assertSee('Welcome!')->assertSee('I have a reservation');
        $this->get(route('public.online-check-in.welcome', ['company' => $company, 'lang' => 'pt', 'choice' => 'new']))->assertOk()
            ->assertSee('Esta opção estará disponível em breve.')->assertSee('name="lang"', false);
        $this->get(route('public.online-check-in.welcome', ['company' => $company, 'lang' => 'fr']))->assertOk()
            ->assertSee('Bienvenue !')->assertSee('J’ai une réservation')->assertSee('Français');
        $this->get(route('public.online-check-in.welcome', ['company' => $company, 'lang' => 'fr', 'choice' => 'new']))->assertOk()
            ->assertSee('Cette option sera bientôt disponible.');
        $this->get('/check-in-online/'.$company->id)->assertNotFound();
        $company->update(['is_active' => false]);
        $this->get(route('public.online-check-in.welcome', $company))->assertNotFound();
    }

    public function test_qr_requires_permission_and_company_access(): void
    {
        $company = Company::factory()->create(['public_slug' => 'hotel-andino']);
        $other = Company::factory()->create(['public_slug' => 'hotel-sur']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('companies.view');
        $this->withoutVite();
        $this->actingAs($user)->get(route('companies.online-check-in.qr', $company))->assertForbidden();
        $user->givePermissionTo('companies.view');
        $this->get(route('companies.online-check-in.qr', $company))->assertOk()
            ->assertSee(route('public.online-check-in.welcome', ['company' => 'hotel-andino']))->assertSee('data:image/svg+xml;base64,', false)
            ->assertSee('/check-in-online/hotel-andino');
        $this->get('/companies/'.$company->id.'/check-in-qr')->assertNotFound();
        $this->get(route('companies.online-check-in.qr', $other))->assertNotFound();
        $company->update(['public_slug' => null]);
        $this->get(route('companies.show', $company))->assertOk()->assertSee('Configura el identificador público');
    }

    public function test_qr_can_use_a_configured_base_url(): void
    {
        $company = Company::factory()->create(['public_slug' => 'hotel-andino']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('companies.view');
        $user->givePermissionTo('companies.view');
        $this->withoutVite();
        config(['app.online_check_in_base_url' => 'http://localhost:8000/']);

        $this->actingAs($user)->get('http://hotel.example/companies/hotel-andino/check-in-qr')
            ->assertOk()->assertSee('http://localhost:8000/check-in-online/hotel-andino');
    }

    public function test_guest_can_search_current_reservations_only_in_the_qr_company(): void
    {
        $company = Company::factory()->create(['public_slug' => 'hotel-andino']);
        $other = Company::factory()->create(['public_slug' => 'hotel-sur']);
        $type = PrivateSpaceType::create(['name' => 'Habitación doble', 'slug' => 'habitacion-doble', 'is_active' => true]);
        $space = Space::factory()->create(['company_id' => $company->id, 'private_space_type_id' => $type->id, 'title' => 'Casa Andina']);
        $room = SpaceRoom::factory()->create(['company_id' => $company->id, 'space_id' => $space->id]);
        $bedType = BedType::query()->firstOrCreate(['slug' => 'cama-matrimonial'], ['name' => 'Cama matrimonial', 'capacity' => 2, 'is_active' => true]);
        RoomBed::create(['company_id' => $company->id, 'space_room_id' => $room->id, 'bed_type_id' => $bedType->id, 'quantity' => 1, 'capacity_per_bed' => 2, 'total_capacity' => 2]);
        $group = ReservationGroup::factory()->create(['company_id' => $company->id, 'code' => 'GROUP-ONE', 'guest_name' => 'María Flores', 'status' => 'confirmed']);
        Reservation::factory()->confirmed()->create(['company_id' => $company->id, 'space_id' => $space->id, 'space_room_id' => $room->id, 'reservation_group_id' => $group->id, 'guest_name' => 'María Flores']);
        Reservation::factory()->confirmed()->create(['company_id' => $company->id, 'space_id' => $space->id, 'space_room_id' => $room->id, 'guest_name' => 'Ana Flores']);
        ReservationGroup::factory()->create(['company_id' => $other->id, 'guest_name' => 'Pedro Flores', 'status' => 'confirmed']);
        ReservationGroup::factory()->create(['company_id' => $company->id, 'code' => 'GROUP-TWO', 'guest_name' => 'Luis Flores', 'status' => 'cancelled']);
        $url = route('public.online-check-in.search', ['company' => $company->public_slug]);

        $this->post($url, ['name' => 'flores', 'lang' => 'es'])->assertOk()
            ->assertSee('Mar*** Flo***')->assertSee('Ana*** Flo***')->assertDontSee('Ped*** Flo***')->assertDontSee('Lui*** Flo***')
            ->assertDontSee('María Flores')->assertSee('Entrada')->assertSee('Salida')->assertSee('Casa Andina')->assertSee('Cama matrimonial');
        $this->post($url, ['name' => 'María', 'lang' => 'fr'])->assertOk()->assertSee('Réservations trouvées');
        $this->post($url, ['name' => '%%%', 'lang' => 'en'])->assertOk()->assertSee('No current reservations found');
        $this->post($url, ['name' => 'ab', 'lang' => 'es'])->assertOk()->assertSee('Escribe entre 3 y 100 caracteres.');
    }

    public function test_guest_can_submit_details_for_reception_review_without_completing_check_in(): void
    {
        $company = Company::factory()->create(['public_slug' => 'hotel-test', 'local_currency' => 'PEN']);
        $reservation = Reservation::factory()->confirmed()->create(['company_id' => $company->id, 'guest_name' => 'Ana Flores', 'total_amount' => 100, 'currency' => 'BOB']);
        $country = Country::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        ExchangeRate::create(['company_id' => $company->id, 'from_currency' => 'USD', 'to_currency' => 'BOB', 'rate' => 4, 'effective_date' => today(), 'is_active' => true]);
        $token = Crypt::encryptString(json_encode(['company' => $company->id, 'kind' => 'single', 'id' => $reservation->id]));
        $form = $this->post(route('public.online-check-in.form', $company), ['token' => $token, 'lang' => 'es']);
        $form->assertOk()->assertSee('100.00 PEN')->assertSee('25.00 USD')->assertSee('Fecha de salida')->assertDontSee('name="birth_date"', false)->assertDontSee('Tipo de cambio')->assertDontSee('1 USD =');
        preg_match('/name="token" value="([^"]+)"/', $form->getContent(), $matches);
        $snapshot = html_entity_decode($matches[1]);
        $other = Company::factory()->create(['public_slug' => 'hotel-other']);
        $this->post(route('public.online-check-in.form', $other), ['token' => $token])->assertNotFound();
        $this->post(route('public.online-check-in.submit', $company), ['token' => $snapshot, 'lang' => 'es', 'document_number' => 'ABC123', 'country_id' => $country->id, 'birth_date' => '1990-01-01'])->assertOk()->assertSee('Revisa los campos obligatorios');
        $this->assertDatabaseCount('online_check_in_submissions', 0);
        $originalCheckout = $reservation->check_out->toDateString();
        $reservation->update(['check_out' => $reservation->check_out->addDay()]);
        $this->post(route('public.online-check-in.submit', $company), ['token' => $snapshot, 'lang' => 'es', 'document_number' => 'ABC123', 'country_id' => $country->id, 'confirm_details' => '1'])->assertStatus(409);
        $reservation->update(['check_out' => $originalCheckout, 'total_amount' => 120]);
        $this->post(route('public.online-check-in.submit', $company), ['token' => $snapshot, 'lang' => 'es', 'document_number' => 'ABC123', 'country_id' => $country->id, 'confirm_details' => '1', 'email' => 'ana@example.com', 'phone' => '123456'])->assertRedirect();
        $this->assertDatabaseHas('online_check_in_submissions', ['company_id' => $company->id, 'reservation_id' => $reservation->id, 'document_number' => 'ABC123', 'email' => 'ana@example.com', 'phone' => '123456', 'price_usd' => 25, 'price_local' => 100, 'local_currency' => 'PEN', 'reviewed_at' => null, 'birth_date' => null]);
        $this->assertSame('confirmed', $reservation->fresh()->status);

        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('occupancy.manage');
        $user->givePermissionTo('occupancy.manage');
        $this->withoutVite();
        $this->actingAs($user)->get(route('online-check-in.review'))->assertOk()->assertSee('ABC123')->assertSee('Ana Flores')->assertDontSee('1 USD =');
        $submission = OnlineCheckInSubmission::query()->firstOrFail();
        $this->post(route('online-check-in.review.store', $submission), ['decision' => 'accepted'])->assertRedirect();
        $this->assertNotNull($submission->fresh()->reviewed_at);
        $this->assertSame('ABC123', $reservation->fresh()->guest_document);
        $otherUser = User::factory()->create(['company_id' => $other->id]);
        $otherUser->givePermissionTo('occupancy.manage');
        $this->actingAs($otherUser)->get(route('online-check-in.review'))->assertOk()->assertDontSee('ABC123');
        $this->post(route('online-check-in.review.store', $submission), ['decision' => 'rejected'])->assertNotFound();
    }

    public function test_contact_is_optional_and_missing_exchange_rate_does_not_block_registration(): void
    {
        $company = Company::factory()->create(['public_slug' => 'sin-tipo-cambio']);
        $reservation = Reservation::factory()->confirmed()->create(['company_id' => $company->id]);
        $country = Country::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $token = Crypt::encryptString(json_encode(['company' => $company->id, 'kind' => 'single', 'id' => $reservation->id]));
        $form = $this->post(route('public.online-check-in.form', $company), ['token' => $token, 'lang' => 'es']);
        $form->assertOk()->assertSee('Los importes se muestran solo como información.')->assertSee('Correo electrónico (opcional)')->assertSee('Teléfono (opcional)')->assertSee('No disponible');
        preg_match('/name="token" value="([^"]+)"/', $form->getContent(), $matches);
        $this->post(route('public.online-check-in.submit', $company), ['token' => html_entity_decode($matches[1]), 'document_number' => '123', 'country_id' => $country->id, 'confirm_details' => '1'])->assertRedirect();
        $this->assertDatabaseHas('online_check_in_submissions', ['reservation_id' => $reservation->id, 'email' => null, 'phone' => null, 'price_usd' => null]);
    }

    public function test_reception_accepts_or_rejects_without_completing_check_in(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('occupancy.manage');
        $user->givePermissionTo('occupancy.manage');
        $country = Country::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $group = ReservationGroup::factory()->create(['company_id' => $company->id, 'status' => 'confirmed', 'check_in' => today(), 'check_out' => today()->addDays(2), 'guest_document' => 'OLD', 'guest_email' => 'old@example.com']);
        $space = Space::factory()->create(['company_id' => $company->id]);
        $reservation = Reservation::factory()->confirmed()->create(['company_id' => $company->id, 'space_id' => $space->id, 'reservation_group_id' => $group->id]);
        $submission = OnlineCheckInSubmission::create(['company_id' => $company->id, 'reservation_group_id' => $group->id, 'guest_name' => $group->guest_name, 'document_number' => 'NEW', 'country_id' => $country->id, 'confirmed_checkout' => $group->check_out, 'confirmed_price' => 100, 'price_currency' => 'BOB', 'local_currency' => 'BOB', 'email' => null, 'phone' => '12345']);
        $this->withoutVite();
        $this->actingAs($user)->get(route('online-check-in.review'))->assertOk()->assertSee('OLD')->assertSee('NEW')->assertSee('old@example.com')->assertSee('No proporcionado');
        $this->post(route('online-check-in.review.store', $submission), ['decision' => 'accepted'])->assertRedirect();
        $this->assertSame('NEW', $group->fresh()->guest_document);
        $this->assertSame('old@example.com', $group->fresh()->guest_email);
        $this->assertSame('12345', $reservation->fresh()->guest_phone);
        $this->assertSame('confirmed', $group->fresh()->status);
        $this->get(route('online-check-in.review'))->assertSee('Ir al check-in');
        $this->get(route('check-ins.create', ['reservation_group_id' => $group->id]))->assertOk()
            ->assertSee('value="NEW"', false)->assertSee('value="old@example.com"', false)->assertSee('value="12345"', false)
            ->assertViewHas('initial', fn (array $initial): bool => (int) $initial['main_guest']['birth_country_id'] === $country->id);
        $this->post(route('online-check-in.review.store', $submission), ['decision' => 'rejected'])->assertStatus(409);

        $rejected = OnlineCheckInSubmission::create(['company_id' => $company->id, 'reservation_group_id' => $group->id, 'guest_name' => $group->guest_name, 'document_number' => 'REJECTED', 'country_id' => $country->id, 'confirmed_checkout' => $group->check_out, 'confirmed_price' => 100, 'price_currency' => 'BOB', 'local_currency' => 'BOB']);
        $this->post(route('online-check-in.review.store', $rejected), ['decision' => 'rejected'])->assertRedirect();
        $this->assertSame('NEW', $group->fresh()->guest_document);
        $this->assertSame('rejected', $rejected->fresh()->review_status);
    }

    public function test_search_finds_first_and_last_name_for_reservations_awaiting_check_in(): void
    {
        $company = Company::factory()->create(['public_slug' => 'hotel-test']);
        $space = Space::factory()->create(['company_id' => $company->id, 'title' => 'Casa Norte']);
        $group = ReservationGroup::factory()->create(['company_id' => $company->id, 'guest_name' => 'Emma Celene Orozco', 'status' => 'checked_in']);
        $block = OccupancyBlock::factory()->create(['company_id' => $company->id, 'space_id' => $space->id, 'status' => 'active']);
        $reservation = Reservation::factory()->confirmed()->create(['company_id' => $company->id, 'space_id' => $space->id, 'reservation_group_id' => $group->id, 'occupancy_block_id' => $block->id, 'guest_name' => 'Emma Celene Orozco', 'status' => 'checked_in']);
        $response = $this->post(route('public.online-check-in.search', $company), ['name' => 'Emma Orozco', 'lang' => 'es']);
        $response->assertOk()->assertSee('Emm*** Cel*** Oro***')->assertSee('Casa Norte');
        $token = Crypt::encryptString(json_encode(['company' => $company->id, 'kind' => 'group', 'id' => $group->id]));
        $this->post(route('public.online-check-in.form', $company), ['token' => $token])->assertOk();
        $country = Country::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $submission = OnlineCheckInSubmission::create(['company_id' => $company->id, 'reservation_group_id' => $group->id, 'guest_name' => $group->guest_name, 'document_number' => 'NEW', 'country_id' => $country->id, 'confirmed_checkout' => $group->check_out, 'confirmed_price' => 100, 'price_currency' => 'BOB', 'local_currency' => 'BOB']);
        $user = User::factory()->create(['company_id' => $company->id]);
        Permission::findOrCreate('occupancy.manage');
        $user->givePermissionTo('occupancy.manage');
        $this->actingAs($user)->post(route('online-check-in.review.store', $submission), ['decision' => 'accepted'])->assertRedirect();
        $this->assertSame('NEW', $reservation->fresh()->guest_document);
        $block->update(['status' => 'cancelled']);
        $this->post(route('public.online-check-in.form', $company), ['token' => $token])->assertNotFound();
        $this->post(route('public.online-check-in.search', $company), ['name' => 'Emma Orozco', 'lang' => 'es'])->assertOk()->assertDontSee('Emm*** Cel*** Oro***');
    }
}
