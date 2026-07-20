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
        $year = (int) fake()->unique()->year();

        return [
            'company_id' => Company::factory(),
            'name' => 'Gestion '.$year,
            'year' => $year,
            'status' => 'active',
            'is_active' => true,
        ];
    }
}
