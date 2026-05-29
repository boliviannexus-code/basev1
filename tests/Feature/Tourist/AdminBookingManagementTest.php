<?php

namespace Tests\Feature\Tourist;

use App\Models\Category;
use App\Models\Company;
use App\Models\Tour;
use App\Models\TourBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminBookingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_view_booking_menu_and_index(): void
    {
        $user = $this->userWithPermission(['dashboard.view', 'bookings.view']);
        $booking = $this->booking();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reservas');

        $this->actingAs($user)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee($booking->email);
    }

    public function test_user_with_permission_can_view_booking_detail(): void
    {
        $user = $this->userWithPermission('bookings.view');
        $booking = $this->booking();

        $this->actingAs($user)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee($booking->booking_code)
            ->assertSee('Datos del turista');
    }

    public function test_user_with_manage_permission_can_update_booking_status(): void
    {
        $user = $this->userWithPermission(['bookings.view', 'bookings.manage']);
        $booking = $this->booking();

        $this->actingAs($user)
            ->patch(route('bookings.status.update', $booking), [
                'status' => TourBooking::STATUS_COMPLETED,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tour_bookings', [
            'id' => $booking->id,
            'status' => TourBooking::STATUS_COMPLETED,
        ]);
    }

    public function test_user_without_booking_permission_cannot_access_admin_bookings(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking();

        $this->actingAs($user)->get(route('bookings.index'))->assertForbidden();
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertForbidden();
    }

    private function userWithPermission(string|array $permissions): User
    {
        foreach ((array) $permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function booking(array $overrides = []): TourBooking
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();
        $tour = Tour::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Tour reservado',
            'title' => 'Tour reservado',
            'status' => Tour::STATUS_ACTIVE,
            'review_status' => Tour::REVIEW_APPROVED,
            'bookings_enabled' => true,
        ]);
        $tourist = User::factory()->create(['email' => 'turista@example.com']);

        return TourBooking::query()->create(array_merge([
            'user_id' => $tourist->id,
            'tour_id' => $tour->id,
            'booking_code' => 'TRV-ADMIN',
            'travel_date' => now()->addDays(3)->toDateString(),
            'people' => 2,
            'first_name' => 'Ana',
            'last_name' => 'Perez',
            'email' => 'ana@example.com',
            'phone' => '70000000',
            'country' => 'Bolivia',
            'unit_price_usd' => 20,
            'total_usd' => 40,
            'status' => TourBooking::STATUS_CONFIRMED,
        ], $overrides));
    }
}
