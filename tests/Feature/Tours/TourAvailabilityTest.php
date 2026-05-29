<?php

namespace Tests\Feature\Tours;

use App\Models\Company;
use App\Models\Tour;
use App\Models\TourAvailability;
use App\Models\TourPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TourAvailabilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_with_permission_can_view_availability_page(): void
    {
        $user = $this->userWithAvailabilityPermission();

        $this
            ->actingAs($user)
            ->get(route('tours.availability.index'))
            ->assertOk()
            ->assertSee('Disponibilidad');
    }

    public function test_user_without_permission_cannot_view_availability_page(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->get(route('tours.availability.index'))
            ->assertForbidden();
    }

    public function test_grid_uses_closed_as_default_status(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $tour = $this->tourForCompany($user->company);

        $this
            ->actingAs($user)
            ->getJson(route('tours.availability.grid', [
                'tour_id' => $tour->id,
                'start_date' => '2026-06-01',
                'days' => 7,
            ]))
            ->assertOk()
            ->assertJsonPath('statuses.'.TourAvailability::STATUS_CLOSED, 'Cerrado')
            ->assertJsonMissingPath('statuses.no_operation')
            ->assertJsonPath('tours.0.days.2026-06-01.status', TourAvailability::STATUS_CLOSED);
    }

    public function test_day_update_creates_single_availability_and_prices(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $tour = $this->tourForCompany($user->company);
        $price = TourPrice::query()->create([
            'tour_id' => $tour->id,
            'title' => 'Adulto',
            'min_people' => 1,
            'max_people' => 4,
            'price_usd' => 25,
        ]);

        $payload = [
            'tour_id' => $tour->id,
            'date' => '2026-06-01',
            'status' => TourAvailability::STATUS_AVAILABLE,
            'capacity' => 12,
            'restrictions' => 'Solo con reserva previa',
            'prices' => [[
                'tour_price_id' => $price->id,
                'title' => 'Adulto',
                'min_people' => 1,
                'max_people' => 4,
                'price_usd' => 30,
            ]],
        ];

        $this->actingAs($user)->patchJson(route('tours.availability.day.update'), $payload)->assertOk();
        $this->actingAs($user)->patchJson(route('tours.availability.day.update'), array_merge($payload, ['capacity' => 15]))->assertOk();

        $this->assertDatabaseCount('tour_availabilities', 1);
        $this->assertDatabaseHas('tour_availabilities', [
            'tour_id' => $tour->id,
            'date' => '2026-06-01',
            'status' => TourAvailability::STATUS_AVAILABLE,
            'capacity' => 15,
        ]);
        $this->assertDatabaseHas('tour_availability_prices', [
            'tour_price_id' => $price->id,
            'title' => 'Adulto',
            'price_usd' => '30.00',
        ]);
    }

    public function test_user_cannot_update_other_company_tour_availability(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $otherCompany = Company::factory()->create();
        $tour = $this->tourForCompany($otherCompany);

        $this
            ->actingAs($user)
            ->patchJson(route('tours.availability.day.update'), [
                'tour_id' => $tour->id,
                'date' => '2026-06-01',
                'status' => TourAvailability::STATUS_AVAILABLE,
            ])
            ->assertNotFound();
    }

    public function test_inactive_tour_availability_cannot_be_updated(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $tour = $this->tourForCompany($user->company, ['status' => Tour::STATUS_INACTIVE]);

        $this
            ->actingAs($user)
            ->patchJson(route('tours.availability.day.update'), [
                'tour_id' => $tour->id,
                'date' => '2026-06-01',
                'status' => TourAvailability::STATUS_AVAILABLE,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tour_id');

        $this->assertDatabaseMissing('tour_availabilities', [
            'tour_id' => $tour->id,
            'date' => '2026-06-01',
        ]);
    }

    public function test_bulk_update_applies_selected_weekdays(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $tour = $this->tourForCompany($user->company);

        $this
            ->actingAs($user)
            ->postJson(route('tours.availability.bulk'), [
                'tour_id' => $tour->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-07',
                'weekdays' => [1, 3],
                'status' => TourAvailability::STATUS_CLOSED,
                'capacity' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2);

        $this->assertDatabaseHas('tour_availabilities', ['tour_id' => $tour->id, 'date' => '2026-06-01', 'status' => TourAvailability::STATUS_CLOSED]);
        $this->assertDatabaseHas('tour_availabilities', ['tour_id' => $tour->id, 'date' => '2026-06-03', 'status' => TourAvailability::STATUS_CLOSED]);
        $this->assertDatabaseMissing('tour_availabilities', ['tour_id' => $tour->id, 'date' => '2026-06-02']);
    }

    public function test_bulk_update_ignores_inactive_tours(): void
    {
        $user = $this->userWithAvailabilityPermission();
        $tour = $this->tourForCompany($user->company, ['status' => Tour::STATUS_INACTIVE]);

        $this
            ->actingAs($user)
            ->postJson(route('tours.availability.bulk'), [
                'tour_id' => $tour->id,
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
                'weekdays' => [1, 2, 3],
                'status' => TourAvailability::STATUS_AVAILABLE,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 0);

        $this->assertDatabaseMissing('tour_availabilities', [
            'tour_id' => $tour->id,
        ]);
    }

    public function test_approved_tour_can_be_enabled_and_disabled(): void
    {
        $user = $this->userWithPermission('tours.edit');
        $tour = $this->tourForCompany($user->company, ['status' => Tour::STATUS_INACTIVE]);

        $this
            ->actingAs($user)
            ->patch(route('tours.toggle-status', $tour))
            ->assertRedirect();

        $this->assertDatabaseHas('tours', [
            'id' => $tour->id,
            'status' => Tour::STATUS_ACTIVE,
        ]);

        $this
            ->actingAs($user)
            ->patch(route('tours.toggle-status', $tour->refresh()))
            ->assertRedirect();

        $this->assertDatabaseHas('tours', [
            'id' => $tour->id,
            'status' => Tour::STATUS_INACTIVE,
        ]);
    }

    private function userWithAvailabilityPermission(): User
    {
        return $this->userWithPermission('tours.availability');
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission);

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permission);
        $user->setRelation('company', $company);

        return $user;
    }

    private function tourForCompany(Company $company, array $overrides = []): Tour
    {
        return Tour::query()->create(array_merge([
            'company_id' => $company->id,
            'name' => 'Tour Demo',
            'title' => 'Tour Demo',
            'description' => 'Demo',
            'status' => Tour::STATUS_ACTIVE,
            'review_status' => Tour::REVIEW_APPROVED,
        ], $overrides));
    }
}
