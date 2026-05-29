<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Division;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Division>
 */
class DivisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => $this->faker->unique()->randomElement([
                'Primera Division',
                'Division Femenina',
                'Division Juvenil',
                'Division Senior',
            ]).' '.$this->faker->unique()->numberBetween(1, 999),
            'min_age' => 15,
            'max_age' => 45,
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
