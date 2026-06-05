<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\DivisionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DivisionCategory>
 */
class DivisionCategoryFactory extends Factory
{
    protected $model = DivisionCategory::class;

    public function definition(): array
    {
        return [
            'division_id' => Division::factory(),
            'company_id' => fn (array $attributes): ?int => Division::query()->find($attributes['division_id'])?->company_id,
            'name' => 'Categoria '.$this->faker->unique()->word(),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
