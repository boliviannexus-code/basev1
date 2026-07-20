<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TournamentRegistration>
 */
class TournamentRegistrationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'tournament_id' => Tournament::factory(),
            'division_id' => Division::factory(),
            'category_id' => fn (array $attributes): ?int => Tournament::query()->find($attributes['tournament_id'])?->category_id
                ?? DivisionCategory::factory()->create([
                    'company_id' => Division::query()->find($attributes['division_id'])?->company_id,
                    'division_id' => $attributes['division_id'],
                ])->id,
            'team_id' => Team::factory(),
            'series' => 'unica',
            'team_number' => fn (array $attributes): int => (int) (TournamentRegistration::query()
                ->where('tournament_id', $attributes['tournament_id'])
                ->where('category_id', $attributes['category_id'] ?? null)
                ->where('series', $attributes['series'] ?? 'unica')
                ->whereNull('deleted_at')
                ->max('team_number') ?? 0) + 1,
            'status' => 'registered',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
