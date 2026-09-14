<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
use App\Models\MatchReport;
use App\Models\Season;
use App\Models\StandingAdjustment;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamSubstitution;
use App\Models\User;
use App\Services\StandingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TournamentModificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_substitution_transfers_slot_fixture_results_and_adjustments(): void
    {
        Permission::findOrCreate('tournament-modifications.view');
        Permission::findOrCreate('tournament-modifications.create');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['tournament-modifications.view', 'tournament-modifications.create']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create(['company_id' => $company->id, 'division_id' => $division->id]);
        $tournament = Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $outgoing = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Saliente']);
        $incoming = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Entrante']);
        $opponent = Team::factory()->create(['company_id' => $company->id, 'name' => 'Rival']);
        $registration = TournamentRegistration::factory()->create([
            'company_id' => $company->id, 'tournament_id' => $tournament->id, 'division_id' => $division->id,
            'category_id' => $category->id, 'team_id' => $outgoing->id, 'series' => 'serie_a', 'team_number' => 1, 'status' => 'registered',
        ]);
        $opponentRegistration = TournamentRegistration::factory()->create([
            'company_id' => $company->id, 'tournament_id' => $tournament->id, 'division_id' => $division->id,
            'category_id' => $category->id, 'team_id' => $opponent->id, 'series' => 'serie_a', 'team_number' => 2, 'status' => 'registered',
        ]);
        $generation = FixtureGeneration::query()->create([
            'company_id' => $company->id, 'tournament_id' => $tournament->id, 'category_id' => $category->id,
            'generated_by' => $user->id, 'status' => 'active', 'config' => ['first_phase_rounds' => 1], 'matches_count' => 1, 'generated_at' => now(),
        ]);
        $match = FixtureMatch::query()->create([
            'company_id' => $company->id, 'fixture_generation_id' => $generation->id, 'tournament_id' => $tournament->id,
            'division_id' => $division->id, 'category_id' => $category->id, 'phase' => 'group', 'stage' => 'Primera fase',
            'stage_order' => 1, 'series' => 'serie_a', 'round_number' => 1, 'match_number' => 1,
            'home_registration_id' => $registration->id, 'away_registration_id' => $opponentRegistration->id,
            'home_team_id' => $outgoing->id, 'away_team_id' => $opponent->id, 'status' => 'pending_schedule',
        ]);
        MatchReport::query()->create([
            'company_id' => $company->id, 'fixture_match_id' => $match->id, 'home_score' => 2, 'away_score' => 0,
            'home_points' => 3, 'away_points' => 0, 'status' => 'completed',
        ]);
        StandingAdjustment::query()->create([
            'company_id' => $company->id, 'tournament_id' => $tournament->id, 'category_id' => $category->id,
            'team_id' => $outgoing->id, 'created_by' => $user->id, 'series' => 'serie_a', 'points_adjustment' => -1, 'reason' => 'Sancion previa',
        ]);

        $this->actingAs($user)->post(route('tournament-modifications.team-substitutions.store'), [
            'tournament_registration_id' => $registration->id,
            'incoming_team_id' => $incoming->id,
            'reason' => 'Cesion formal del cupo por abandono del equipo saliente.',
            'confirm_substitution' => '1',
        ])->assertRedirect(route('tournament-modifications.index'));

        $this->assertDatabaseHas('tournament_registrations', ['id' => $registration->id, 'team_id' => $incoming->id]);
        $this->assertDatabaseHas('fixture_matches', ['id' => $match->id, 'home_team_id' => $incoming->id]);
        $this->assertDatabaseHas('standing_adjustments', ['team_id' => $incoming->id, 'points_adjustment' => -1]);
        $this->assertDatabaseHas('tournament_team_substitutions', ['outgoing_team_id' => $outgoing->id, 'incoming_team_id' => $incoming->id]);

        $row = app(StandingsService::class)->table($tournament, $category->id, 'serie_a')->firstWhere('team_id', $incoming->id);
        $this->assertSame(1, $row['played']);
        $this->assertSame(2, $row['final_points']);
        $this->assertSame(1, TournamentTeamSubstitution::query()->count());
    }
}
