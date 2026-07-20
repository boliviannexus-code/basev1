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
                'category_id' => $firstTournament->category_id,
                'team_id' => $team->id,
                'series' => 'serie_a',
                'status' => 'registered',
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $secondTournament->id,
                'category_id' => $secondTournament->category_id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this->assertDatabaseCount('tournament_registrations', 2);
        $this->assertDatabaseHas('tournament_registrations', [
            'tournament_id' => $firstTournament->id,
            'team_id' => $team->id,
            'series' => 'serie_a',
        ]);
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
                'category_id' => $tournament->category_id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertSessionHasErrors('team_id');
    }

    public function test_team_numbers_are_assigned_by_registration_order_per_series(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $tournament = $this->tournamentFor($company);
        $teams = Team::factory()->count(4)->create(['company_id' => $company->id]);
        $seriesByTeam = ['serie_a', 'serie_a', 'serie_b', 'serie_b'];

        foreach ($teams->values() as $index => $team) {
            $this
                ->actingAs($user)
                ->post(route('tournament-registrations.store'), [
                    'tournament_id' => $tournament->id,
                    'category_id' => $tournament->category_id,
                    'team_id' => $team->id,
                    'series' => $seriesByTeam[$index],
                    'status' => 'registered',
                ])
                ->assertRedirect(route('tournament-registrations.index'));
        }

        foreach ($teams->values() as $index => $team) {
            $expectedNumber = $index % 2 === 0 ? 1 : 2;

            $this->assertDatabaseHas('tournament_registrations', [
                'tournament_id' => $tournament->id,
                'team_id' => $team->id,
                'series' => $seriesByTeam[$index],
                'team_number' => $expectedNumber,
            ]);
        }
    }

    public function test_team_number_can_be_updated_inline_and_must_be_unique_per_category_series(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.update']);
        $tournament = $this->tournamentFor($company);
        $firstTeam = Team::factory()->create(['company_id' => $company->id]);
        $secondTeam = Team::factory()->create(['company_id' => $company->id]);
        $thirdTeam = Team::factory()->create(['company_id' => $company->id]);
        $firstRegistration = TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $firstTeam->id,
            'series' => 'serie_a',
            'team_number' => 1,
        ]);
        $secondRegistration = TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $secondTeam->id,
            'series' => 'serie_a',
            'team_number' => 2,
        ]);
        $thirdRegistration = TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $thirdTeam->id,
            'series' => 'serie_b',
            'team_number' => 1,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(route('tournament-registrations.team-number.update', $secondRegistration), [
                'team_number' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('data.team_number', 5);

        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $secondRegistration->id,
            'team_number' => 5,
        ]);

        $this
            ->actingAs($user)
            ->patchJson(route('tournament-registrations.team-number.update', $secondRegistration), [
                'team_number' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('data.team_number.0', 'Ya existe otro equipo con este numero en esta categoria y serie.');

        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $firstRegistration->id,
            'team_number' => 1,
        ]);
        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $secondRegistration->id,
            'team_number' => 5,
        ]);
        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $thirdRegistration->id,
            'team_number' => 1,
        ]);
    }

    public function test_team_number_is_reassigned_when_registration_moves_to_another_series(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.update']);
        $tournament = $this->tournamentFor($company);
        $movingTeam = Team::factory()->create(['company_id' => $company->id]);
        $existingTeam = Team::factory()->create(['company_id' => $company->id]);
        $movingRegistration = TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $movingTeam->id,
            'series' => 'serie_a',
            'team_number' => 1,
        ]);
        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $existingTeam->id,
            'series' => 'serie_b',
            'team_number' => 1,
        ]);

        $this
            ->actingAs($user)
            ->put(route('tournament-registrations.update', $movingRegistration), [
                'category_id' => $tournament->category_id,
                'series' => 'serie_b',
                'status' => 'registered',
                'notes' => null,
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this->assertDatabaseHas('tournament_registrations', [
            'id' => $movingRegistration->id,
            'series' => 'serie_b',
            'team_number' => 2,
        ]);
    }

    public function test_team_can_register_to_different_tournaments_in_same_division(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $firstTournament = $this->tournamentFor($company, 'Sub 17', $division);
        $secondTournament = $this->tournamentFor($company, 'Sub 20', $division);

        $this->actingAs($user)->post(route('tournament-registrations.store'), [
            'tournament_id' => $firstTournament->id,
            'category_id' => $firstTournament->category_id,
            'team_id' => $team->id,
            'status' => 'registered',
        ]);

        $this
            ->actingAs($user)
            ->post(route('tournament-registrations.store'), [
                'tournament_id' => $secondTournament->id,
                'category_id' => $secondTournament->category_id,
                'team_id' => $team->id,
                'status' => 'registered',
            ])
            ->assertRedirect(route('tournament-registrations.index'));

        $this->assertDatabaseCount('tournament_registrations', 2);
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
                'category_id' => $otherTournament->category_id,
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
            ->assertSee('EQUIPO PROPIO')
            ->assertDontSee('EQUIPO AJENO');
    }

    public function test_registrations_index_starts_from_existing_teams(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['tournament-registrations.view', 'tournament-registrations.create']);
        $historicTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Historico']);
        Team::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Equipo Ajeno']);

        $this
            ->actingAs($user)
            ->get(route('tournament-registrations.index'))
            ->assertOk()
            ->assertSee('Equipos disponibles para inscripcion')
            ->assertSee('EQUIPO HISTORICO')
            ->assertSee(route('tournament-registrations.create', ['team_id' => $historicTeam->id]), false)
            ->assertDontSee('EQUIPO AJENO');
    }

    public function test_tournament_categories_endpoint_only_returns_enabled_tournament_categories(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, 'Sub 17');
        $otherCategory = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $tournament->division_id,
            'name' => 'Sub 20',
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('tournament-registrations.tournaments.categories', [
                'tournament' => $tournament,
                'team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertJsonPath('already_registered', false)
            ->assertSee('Sub 17')
            ->assertDontSee('Sub 20');

        $this->assertNotNull($otherCategory->id);
    }

    public function test_tournament_categories_endpoint_marks_already_registered_team(): void
    {
        [$company, , $user] = $this->leagueUser(['tournament-registrations.create']);
        $team = Team::factory()->create(['company_id' => $company->id]);
        $tournament = $this->tournamentFor($company, 'Sub 17');

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $team->id,
        ]);

        $this
            ->actingAs($user)
            ->getJson(route('tournament-registrations.tournaments.categories', [
                'tournament' => $tournament,
                'team_id' => $team->id,
            ]))
            ->assertOk()
            ->assertJsonPath('already_registered', true)
            ->assertJsonCount(0, 'data');
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
            ->assertSee('EQUIPO TAB');
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
            ->assertSee('AUTOCOMPLETE PROPIO')
            ->assertDontSee('AUTOCOMPLETE AJENO');
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
