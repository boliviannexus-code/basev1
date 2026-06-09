<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Country;
use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'document_type' => 'ci',
            'document_number' => fake()->unique()->numerify('########'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => now()->subYears(30)->toDateString(),
            'birth_country_id' => Country::query()->first()?->id,
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->safeEmail(),
        ];
    }
}
