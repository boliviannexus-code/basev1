<?php

namespace Database\Factories;

use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Season;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tournament>
 */
class TournamentFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Tournament $tournament): void {
            if ($tournament->category_id) {
                $tournament->categories()->syncWithoutDetaching([$tournament->category_id]);
            }
        });
    }

    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'company_id' => fn (array $attributes): ?int => Season::query()->find($attributes['season_id'])?->company_id,
            'division_id' => fn (array $attributes): int => Division::factory()
                ->create(['company_id' => Season::query()->find($attributes['season_id'])?->company_id])
                ->id,
            'category_id' => fn (array $attributes): int => DivisionCategory::factory()
                ->create([
                    'company_id' => Division::query()->find($attributes['division_id'])?->company_id,
                    'division_id' => $attributes['division_id'],
                ])
                ->id,
            'name' => 'Torneo '.$this->faker->unique()->word(),
            'status' => 'planned',
            'is_active' => true,
        ];
    }
}
