<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true).' Liga Deportiva',
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'country' => 'Bolivia',
            'foundation_date' => fake()->optional()->date(),
            'legal_personality' => fake()->optional()->numerify('P.J. ####/####'),
            'interest_data' => fake()->optional()->sentence(),
            'report_footer' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
