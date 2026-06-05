<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Division;
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
            'team_id' => Team::factory(),
            'status' => 'registered',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
