<?php

namespace Tests\Feature\Locations;

use App\Models\User;
use Tests\TestCase;

class LocalLocationSearchTest extends TestCase
{
    public function test_authenticated_user_can_search_countries(): void
    {
        $user = $this->authenticatedUser();

        $this
            ->actingAs($user)
            ->getJson(route('locations.search', [
                'type' => 'country',
                'q' => 'bol',
            ]))
            ->assertOk()
            ->assertJsonPath('data.0.value', 'Bolivia')
            ->assertJsonPath('data.0.country_code', 'BO');
    }

    public function test_city_search_can_be_filtered_by_country_and_ignores_accents(): void
    {
        $user = $this->authenticatedUser();

        $this
            ->actingAs($user)
            ->getJson(route('locations.search', [
                'type' => 'city',
                'country' => 'Bolivia',
                'q' => 'potosi',
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'city' => 'Potosi',
                'country' => 'Bolivia',
                'country_code' => 'BO',
            ]);
    }

    public function test_guests_can_search_locations_for_public_filters(): void
    {
        $this
            ->getJson(route('locations.search', ['q' => 'La Paz']))
            ->assertOk()
            ->assertJsonFragment([
                'city' => 'La Paz',
                'country' => 'Bolivia',
            ]);
    }

    private function authenticatedUser(): User
    {
        $user = new User([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);
        $user->id = 1;
        $user->exists = true;

        return $user;
    }
}
