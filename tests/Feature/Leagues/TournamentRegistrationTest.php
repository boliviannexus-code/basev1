<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TournamentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_can_register_to_more_than_one_tournament(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $firstTournament = $this->tournamentFor($company, 'Sub 17');
        $secondTournament = $this->tournamentFor($company, 'Sub 20');

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $firstTournament->id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $secondTournament->id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this->assertDatabaseCount('tournament_registrations', 2);
    }

    public function test_team_cannot_register_twice_to_same_tournament(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company);

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
        ]);

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertSessionHasErrors('team_id');
    }

    public function test_team_cannot_register_twice_in_same_division_even_with_different_tournaments(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $firstTournament = $this->tournamentFor($company, 'Sub 17', $division);
        $secondTournament = $this->tournamentFor($company, 'Sub 20', $division);

        $this->actingAs($user)->post(route('tournament-registrations.store'), [
            'tournament_id' => $firstTournament->id,
            'team_id' => $team->id,
            'status' => 'registered',
        ]);

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $secondTournament->id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertSessionHasErrors('team_id');
    }

    public function test_team_and_tournament_must_belong_to_same_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $otherTournament = $this->tournamentFor($otherCompany);

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $otherTournament->id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertSessionHasErrors('tournament_id');
    }

    public function test_league_user_only_sees_own_registrations(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournament-registrations.view']);
        $ownTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Propio']);
        $otherTeam = Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Equipo Ajeno']);
        $ownTournament = $this->tournamentFor($company);
        $otherTournament = $this->tournamentFor($otherCompany);

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $ownTournament->id,
            'division_id' => $ownTournament->division_id,
            'team_id' => $ownTeam->id,
        ]);
        TournamentRegistration::factory()->create([
            'company_id' => $otherCompany->id,
            'tournament_id' => $otherTournament->id,
            'division_id' => $otherTournament->division_id,
            'team_id' => $otherTeam->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('tournament-registrations.index'))
            ->assertOk()
            ->assertSee('Equipo Propio')
            ->assertDontSee('Equipo Ajeno');
    }

    public function test_registrations_index_groups_teams_by_tournament_tabs(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.view']);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Tab']);
        $tournament = $this->tournamentFor($company, 'Sub 23');

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'team_id' => $team->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('tournament-registrations.index'))
            ->assertOk()
            ->assertSee('nav-tabs', false)
            ->assertSee($tournament->name)
            ->assertSee('Equipo Tab');
    }

    public function test_registrations_index_only_shows_divisions_with_registered_teams(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.view']);
        $firstDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Primera']);
        $secondDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Segunda']);
        $firstTournament = $this->tournamentFor($company, 'Sub 17', $firstDivision);
        $secondTournament = $this->tournamentFor($company, 'Sub 20', $secondDivision);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Primera']);

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $firstTournament->id,
            'division_id' => $firstTournament->division_id,
            'team_id' => $team->id,
        ]);

        $this
            ->actingAs($user)
            ->get(route('tournament-registrations.index', ['division_id' => $firstDivision->id]))
            ->assertOk()
            ->assertSee('registration-division-tabs', false)
            ->assertSee('registration-tournament-tabs', false)
            ->assertSee($firstDivision->name)
            ->assertSee($firstTournament->name)
            ->assertDontSee($secondDivision->name)
            ->assertDontSee($secondTournament->name);
    }

    public function test_team_autocomplete_is_scoped_to_tournament_league(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournament-registrations.create']);
        $tournament = $this->tournamentFor($company);
        Team::factory()->create(['company_id' => $company->id, 'name' => 'Autocomplete Propio']);
        Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Autocomplete Ajeno']);

        $this
            ->actingAs($user)
            ->getJson(route('tournament-registrations.teams.search', [
                'q' => 'Autocomplete',
                'tournament_id' => $tournament->id,
            ]))
            ->assertOk()
            ->assertSee('Autocomplete Propio')
            ->assertDontSee('Autocomplete Ajeno');
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }

    private function tournamentFor(Company $company, string $categoryName = 'Sub 17', ?Division $division = null): Tournament
    {
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division ??= Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'name' => $categoryName,
        ]);

        return Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => $division->name.' - '.$category->name.' - '.$season->name,
        ]);
    }
}
