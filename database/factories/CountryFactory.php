<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'iso_code' => strtoupper(fake()->unique()->countryCode()),
            'name' => fake()->country(),
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => fake()->numberBetween(10, 250),
        ];
    }
}
