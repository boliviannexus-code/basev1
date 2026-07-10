<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Court>
 */
class CourtFactory extends Factory
{
    protected $model = Court::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => 'Cancha '.$this->faker->unique()->numberBetween(1, 999),
            'address' => $this->faker->optional()->address(),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
