<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Gestion '.$this->faker->unique()->year(),
            'year' => (int) $this->faker->year(),
            'status' => 'planned',
            'is_active' => true,
        ];
    }
}
