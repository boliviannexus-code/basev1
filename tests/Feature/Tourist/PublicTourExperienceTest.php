<?php

namespace Tests\Feature\Tourist;

use App\Models\Category;
use App\Models\Company;
use App\Models\Tour;
use App\Models\TourAvailability;
use App\Models\TourBooking;
use App\Models\TourPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicTourExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_show_only_bookable_tours(): void
    {
        $visible = $this->bookableTour(['title' => 'Aventura en Uyuni']);
        $this->bookableTour(['title' => 'Tour oculto', 'status' => Tour::STATUS_INACTIVE]);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Aventura en Uyuni')
            ->assertDontSee('Tour oculto');

        $this->get(route('public.tours.index'))
            ->assertOk()
            ->assertSee($visible->display_title)
            ->assertDontSee('Tour oculto');

        $this->get(route('public.tours.show', $visible))
            ->assertOk()
            ->assertSee('Reservar ahora');
    }

    public function test_marketplace_filters_by_category_destination_date_people_and_price(): void
    {
        $category = Category::factory()->create(['name' => 'Naturaleza']);
        $match = $this->bookableTour([
            'title' => 'Lagunas de La Paz',
            'city' => 'La Paz',
            'category_id' => $category->id,
        ]);
        $this->availability($match, '2026-06-10', 6, 25);
        $this->bookableTour(['title' => 'Ruta del vino', 'city' => 'Tarija']);

        $this->get(route('public.tours.index', [
            'destination' => 'La Paz',
            'category' => $category->id,
            'date' => '2026-06-10',
            'people' => 4,
            'max_price' => 30,
        ]))
            ->assertOk()
            ->assertSee('Lagunas de La Paz')
            ->assertDontSee('Ruta del vino');
    }

    public function test_tourist_can_register_and_is_redirected_to_reservations(): void
    {
        $this->post(route('tourist.register.store'), [
            'name' => 'Ana Turista',
            'email' => 'ana@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertRedirect(route('tourist.reservations.index'));

        $this->assertAuthenticated();
        $this->assertTrue(User::query()->where('email', 'ana@example.com')->first()->hasRole('tourist'));
    }

    public function test_tourist_can_create_booking_and_capacity_is_updated(): void
    {
        $tour = $this->bookableTour(['title' => 'City tour premium']);
        $availability = $this->availability($tour, now()->addDays(5)->toDateString(), 4, 40);
        $user = $this->tourist();

        $this->actingAs($user)
            ->post(route('public.bookings.store', $tour), [
                'travel_date' => $availability->date->toDateString(),
                'people' => 2,
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'email' => 'ana@example.com',
                'phone' => '70000000',
                'country' => 'Bolivia',
                'special_requirements' => 'Vegetariana',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tour_bookings', [
            'tour_id' => $tour->id,
            'user_id' => $user->id,
            'people' => 2,
            'total_usd' => '80.00',
            'status' => TourBooking::STATUS_CONFIRMED,
        ]);
        $this->assertSame(2, $availability->refresh()->booked_count);
    }

    public function test_booking_uses_individual_price_as_fallback_for_multiple_people(): void
    {
        $tour = $this->bookableTour(['title' => 'Base individual']);
        $tour->prices()->update(['max_people' => 1, 'price_usd' => 20]);
        $availability = $this->availability($tour, now()->addDays(5)->toDateString(), 5, 20, 1);
        $user = $this->tourist();

        $this->actingAs($user)
            ->post(route('public.bookings.store', $tour), $this->bookingPayload($availability, ['people' => 2]))
            ->assertRedirect();

        $this->assertDatabaseHas('tour_bookings', [
            'tour_id' => $tour->id,
            'people' => 2,
            'unit_price_usd' => '20.00',
            'total_usd' => '40.00',
        ]);
    }

    public function test_booking_uses_group_price_when_matching_range_exists(): void
    {
        $tour = $this->bookableTour(['title' => 'Descuento grupal']);
        $availability = $this->availability($tour, now()->addDays(5)->toDateString(), 8, 20, 1);
        $availability->prices()->create([
            'tour_price_id' => null,
            'title' => 'Grupo',
            'min_people' => 2,
            'max_people' => null,
            'price_usd' => 18,
        ]);
        $user = $this->tourist();

        $this->actingAs($user)
            ->post(route('public.bookings.store', $tour), $this->bookingPayload($availability, ['people' => 5]))
            ->assertRedirect();

        $this->assertDatabaseHas('tour_bookings', [
            'tour_id' => $tour->id,
            'people' => 5,
            'unit_price_usd' => '18.00',
            'total_usd' => '90.00',
        ]);
    }

    public function test_availability_special_price_has_priority_over_general_tour_price(): void
    {
        $tour = $this->bookableTour(['title' => 'Precio especial']);
        $tour->prices()->create([
            'title' => 'Grupo general',
            'min_people' => 2,
            'max_people' => null,
            'price_usd' => 18,
        ]);
        $availability = $this->availability($tour, now()->addDays(5)->toDateString(), 6, 20, 1);
        $availability->prices()->create([
            'tour_price_id' => null,
            'title' => 'Grupo fecha especial',
            'min_people' => 2,
            'max_people' => null,
            'price_usd' => 15,
        ]);
        $user = $this->tourist();

        $this->actingAs($user)
            ->post(route('public.bookings.store', $tour), $this->bookingPayload($availability, ['people' => 2]))
            ->assertRedirect();

        $this->assertDatabaseHas('tour_bookings', [
            'tour_id' => $tour->id,
            'people' => 2,
            'unit_price_usd' => '15.00',
            'total_usd' => '30.00',
        ]);
    }

    public function test_booking_rejects_sold_out_or_unavailable_dates(): void
    {
        $tour = $this->bookableTour();
        $availability = $this->availability($tour, now()->addDays(3)->toDateString(), 1, 20);
        $user = $this->tourist();

        $this->actingAs($user)
            ->from(route('public.bookings.create', [$tour, 'date' => $availability->date->toDateString(), 'people' => 2]))
            ->post(route('public.bookings.store', $tour), [
                'travel_date' => $availability->date->toDateString(),
                'people' => 2,
                'first_name' => 'Ana',
                'last_name' => 'Perez',
                'email' => 'ana@example.com',
                'phone' => '70000000',
                'country' => 'Bolivia',
            ])
            ->assertSessionHasErrors('people');
    }

    public function test_tourist_cannot_view_another_tourist_booking_or_admin_dashboard(): void
    {
        $tour = $this->bookableTour();
        $owner = $this->tourist();
        $other = $this->tourist(['email' => 'other@example.com']);
        $booking = TourBooking::query()->create([
            'user_id' => $owner->id,
            'tour_id' => $tour->id,
            'booking_code' => 'TRV-TEST',
            'travel_date' => now()->addDay()->toDateString(),
            'people' => 1,
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'email' => 'ana@example.com',
            'phone' => '70000000',
            'country' => 'Bolivia',
            'unit_price_usd' => 20,
            'total_usd' => 20,
            'status' => TourBooking::STATUS_CONFIRMED,
        ]);

        $this->actingAs($other)
            ->get(route('tourist.reservations.show', $booking))
            ->assertForbidden();

        $this->actingAs($other)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    private function tourist(array $overrides = []): User
    {
        Role::findOrCreate('tourist', 'web');
        $user = User::factory()->create($overrides);
        $user->assignRole('tourist');

        return $user;
    }

    private function bookableTour(array $overrides = []): Tour
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();

        $tour = Tour::query()->create(array_merge([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Tour publico',
            'title' => 'Tour publico',
            'description' => 'Descripcion del tour',
            'short_description' => 'Experiencia de prueba',
            'city' => 'La Paz',
            'country' => 'Bolivia',
            'duration' => '1 dia',
            'status' => Tour::STATUS_ACTIVE,
            'review_status' => Tour::REVIEW_APPROVED,
            'bookings_enabled' => true,
        ], $overrides));

        TourPrice::query()->create([
            'tour_id' => $tour->id,
            'title' => 'Adulto',
            'min_people' => 1,
            'max_people' => null,
            'price_usd' => 20,
        ]);

        return $tour;
    }

    private function availability(Tour $tour, string $date, int $capacity, float $price, ?int $maxPeople = null): TourAvailability
    {
        $availability = TourAvailability::query()->create([
            'tour_id' => $tour->id,
            'date' => $date,
            'status' => TourAvailability::STATUS_AVAILABLE,
            'capacity' => $capacity,
            'booked_count' => 0,
        ]);

        $availability->prices()->create([
            'tour_price_id' => $tour->prices()->first()->id,
            'title' => 'Adulto',
            'min_people' => 1,
            'max_people' => $maxPeople,
            'price_usd' => $price,
        ]);

        return $availability;
    }

    private function bookingPayload(TourAvailability $availability, array $overrides = []): array
    {
        return array_merge([
            'travel_date' => $availability->date->toDateString(),
            'people' => 1,
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'email' => 'ana@example.com',
            'phone' => '70000000',
            'country' => 'Bolivia',
        ], $overrides);
    }
}
