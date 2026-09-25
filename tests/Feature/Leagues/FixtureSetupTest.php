<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use App\Models\MatchReport;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FixtureSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_flow_lists_tournaments_categories_series_and_stepper(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['fixtures.view']);
        $tournament = $this->tournamentFor($company);
        $otherTournament = $this->tournamentFor($otherCompany);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Alfa']);
        $secondTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Beta']);
        $thirdTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Gamma']);
        $fourthTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo Delta']);

        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $firstTeam->id,
            'series' => 'serie_a',
            'team_number' => 1,
        ]);
        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $secondTeam->id,
            'series' => 'serie_a',
            'team_number' => 2,
        ]);
        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $thirdTeam->id,
            'series' => 'serie_b',
            'team_number' => 1,
        ]);
        TournamentRegistration::factory()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'team_id' => $fourthTeam->id,
            'series' => 'serie_b',
            'team_number' => 2,
        ]);

        $this
            ->actingAs($user)
            ->get(route('fixtures.index'))
            ->assertOk()
            ->assertSee('Fixture')
            ->assertSee($tournament->name)
            ->assertDontSee($otherTournament->name);

        $this
            ->actingAs($user)
            ->get(route('fixtures.categories', $tournament))
            ->assertOk()
            ->assertSee($tournament->category->name)
            ->assertSee('Con equipos');

        $this
            ->actingAs($user)
            ->get(route('fixtures.series', ['tournament' => $tournament, 'category' => $tournament->category]))
            ->assertOk()
            ->assertSee('Serie A')
            ->assertSee('Serie B')
            ->assertSee('Configurar modalidad')
            ->assertSee('Ver equipos')
            ->assertDontSee('EQUIPO ALFA')
            ->assertDontSee('EQUIPO BETA');

        $this
            ->actingAs($user)
            ->get(route('fixtures.series.teams', [
                'tournament' => $tournament,
                'category' => $tournament->category,
                'series' => 'serie_a',
            ]))
            ->assertOk()
            ->assertSee('EQUIPO ALFA')
            ->assertSee('EQUIPO BETA')
            ->assertDontSee('EQUIPO GAMMA');

        $this
            ->actingAs($user)
            ->get(route('fixtures.configure', [
                'tournament' => $tournament,
                'category' => $tournament->category,
            ]))
            ->assertOk()
            ->assertSee('Generar primera fase')
            ->assertSee('La clasificacion y la modalidad de segunda fase se definiran por separado')
            ->assertSee('Serie A: 2 equipos')
            ->assertSee('Serie B: 2 equipos')
            ->assertDontSee('Ver equipos')
            ->assertDontSee('EQUIPO ALFA')
            ->assertDontSee('EQUIPO BETA')
            ->assertSee('Solo ida')
            ->assertSee('Ida y vuelta')
            ->assertSee('Generar y guardar primera fase')
            ->assertDontSee('Clasificados por serie');
    }

    public function test_fixture_flow_requires_view_permission(): void
    {
        Permission::findOrCreate('fixtures.view');
        [$company, , $user] = $this->leagueUser([]);
        $tournament = $this->tournamentFor($company);

        $this
            ->actingAs($user)
            ->get(route('fixtures.categories', $tournament))
            ->assertForbidden();
    }

    public function test_fixture_patterns_are_printable(): void
    {
        [, , $user] = $this->leagueUser(['fixtures.view']);

        $this
            ->actingAs($user)
            ->get(route('fixtures.patterns'))
            ->assertOk()
            ->assertSee('Plantillas de fixture')
            ->assertSee('3 equipos')
            ->assertSee('4 equipos')
            ->assertSee('5 equipos')
            ->assertSee('6 equipos')
            ->assertSee('12 equipos')
            ->assertDontSee('13 equipos')
            ->assertDontSee('20 equipos')
            ->assertSee('Fecha 1')
            ->assertSee('(1)')
            ->assertDontSee('Imprimir / guardar PDF')
            ->assertSee('Descargar PDF');

        $pdfResponse = $this
            ->actingAs($user)
            ->get(route('fixtures.patterns.pdf'));

        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());
    }

    public function test_first_and_second_phase_can_be_generated_independently(): void
    {
        [$company, , $user] = $this->leagueUser(['fixtures.view', 'fixtures.generate']);
        $tournament = $this->tournamentFor($company);

        foreach (['serie_a', 'serie_b'] as $series) {
            foreach (range(1, 2) as $number) {
                TournamentRegistration::factory()->create([
                    'company_id' => $company->id,
                    'tournament_id' => $tournament->id,
                    'division_id' => $tournament->division_id,
                    'category_id' => $tournament->category_id,
                    'team_id' => Team::factory()->create(['company_id' => $company->id])->id,
                    'series' => $series,
                    'team_number' => $number,
                ]);
            }
        }

        $response = $this
            ->actingAs($user)
            ->post(route('fixtures.generate', [
                'tournament' => $tournament,
                'category' => $tournament->category,
            ]), [
                'first_phase_rounds' => 1,
            ]);

        $generation = FixtureGeneration::query()->firstOrFail();

        $response->assertRedirect(route('fixtures.report', $generation));
        $this->assertSame(2, $generation->matches_count);
        $this->assertSame(2, FixtureMatch::query()->where('phase', 'group')->count());
        $this->assertSame(0, FixtureMatch::query()->where('phase', 'knockout')->count());
        $this->assertSame('pending', $generation->config['second_phase_status']);

        $this
            ->actingAs($user)
            ->get(route('fixtures.configure', ['tournament' => $tournament, 'category' => $tournament->category]))
            ->assertOk()
            ->assertSee('Primera fase guardada')
            ->assertSee('Definir clasificacion y segunda fase')
            ->assertSee('Clasificados por serie');

        $secondPhaseResponse = $this
            ->actingAs($user)
            ->post(route('fixtures.second-phase.generate', $generation), [
                'qualifiers_per_series' => 2,
                'second_phase_mode' => 'knockout',
                'fill_rule' => 'best_thirds',
                'third_place' => 0,
                'stage_leg_modes' => [
                    'semifinal' => 'single',
                    'final' => 'single',
                ],
            ]);

        $secondPhaseResponse->assertRedirect(route('fixtures.report', $generation));
        $generation->refresh();
        $this->assertSame(5, $generation->matches_count);
        $this->assertSame('configured', $generation->config['second_phase_status']);
        $this->assertSame(2, FixtureMatch::query()->where('phase', 'group')->count());
        $this->assertSame(3, FixtureMatch::query()->where('phase', 'knockout')->count());
        $this->assertDatabaseHas('fixture_matches', [
            'fixture_generation_id' => $generation->id,
            'stage' => 'Semifinal',
            'home_seed' => '1 Serie A',
            'away_seed' => '2 Serie B',
        ]);
        $this->assertDatabaseHas('fixture_matches', [
            'fixture_generation_id' => $generation->id,
            'stage' => 'Final',
            'home_seed' => 'Ganador Semifinal 1',
            'away_seed' => 'Ganador Semifinal 2',
            'status' => 'pending_schedule',
        ]);

        $this
            ->actingAs($user)
            ->get(route('fixtures.report', $generation))
            ->assertOk()
            ->assertSee('Fixture generado')
            ->assertDontSee('Imprimir / guardar PDF')
            ->assertSee('Imprimir fixture completo')
            ->assertSee('Imprimir por equipo')
            ->assertSee('Pendiente de programacion')
            ->assertSee('Ganador Semifinal 1');

        $pdfResponse = $this
            ->actingAs($user)
            ->get(route('fixtures.report.pdf', $generation));

        $pdfResponse->assertOk();
        $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdfResponse->getContent());

        $teamPdfResponse = $this
            ->actingAs($user)
            ->get(route('fixtures.report.teams-pdf', $generation));

        $teamPdfResponse->assertOk();
        $this->assertSame('application/pdf', $teamPdfResponse->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $teamPdfResponse->getContent());

        $this
            ->actingAs($user)
            ->get(route('fixtures.configure', ['tournament' => $tournament, 'category' => $tournament->category]))
            ->assertOk()
            ->assertSee('Ver fixture generado')
            ->assertSee('La primera y segunda fase ya estan definidas');
    }

    public function test_return_leg_preserves_first_leg_and_rejects_duplicates(): void
    {
        [$company, , $user] = $this->leagueUser(['fixtures.view', 'fixtures.generate']);
        $tournament = $this->tournamentFor($company);
        foreach (['serie_a' => 3, 'serie_b' => 2] as $series => $count) {
            foreach (range(1, $count) as $number) {
                TournamentRegistration::factory()->create([
                    'company_id' => $company->id,
                    'tournament_id' => $tournament->id,
                    'division_id' => $tournament->division_id,
                    'category_id' => $tournament->category_id,
                    'team_id' => Team::factory()->create(['company_id' => $company->id])->id,
                    'series' => $series,
                    'team_number' => $number,
                ]);
            }
        }
        $this->actingAs($user)->post(route('fixtures.generate', [
            'tournament' => $tournament, 'category' => $tournament->category,
        ]), ['first_phase_rounds' => 1])->assertSessionHasNoErrors();
        $generation = FixtureGeneration::query()->firstOrFail();
        $first = $generation->matches()->firstOrFail();
        $first->update(['status' => 'completed']);
        $report = MatchReport::query()->create([
            'company_id' => $company->id, 'fixture_match_id' => $first->id,
            'home_score' => 2, 'away_score' => 1, 'status' => 'completed',
        ]);
        $original = $generation->matches()->orderBy('match_number')->get();
        $configure = route('fixtures.configure', ['tournament' => $tournament, 'category' => $tournament->category]);
        $this->get($configure)->assertSee('Generar vuelta de la primera fase');

        $route = route('fixtures.return-leg.generate', $generation);
        $this->post($route)->assertSessionHasNoErrors()->assertRedirect(route('fixtures.report', $generation));
        $this->assertSame(8, $generation->refresh()->matches_count);
        $this->assertSame(2, $generation->config['first_phase_rounds']);
        $this->assertSame($original->toArray(), $generation->matches()->where('leg_number', 1)->orderBy('match_number')->get()->toArray());
        foreach ($original as $match) {
            $this->assertDatabaseHas('fixture_matches', [
                'fixture_generation_id' => $generation->id,
                'series' => $match->series, 'leg_number' => 2,
                'home_registration_id' => $match->away_registration_id,
                'away_registration_id' => $match->home_registration_id,
                'home_team_id' => $match->away_team_id, 'away_team_id' => $match->home_team_id,
                'round_number' => $match->round_number + ($match->series === 'serie_a' ? 3 : 1),
                'status' => 'pending_schedule', 'matchday_date_id' => null, 'scheduled_time' => null,
            ]);
        }
        $this->assertDatabaseHas('match_reports', ['id' => $report->id, 'home_score' => 2]);
        $this->post($route)->assertSessionHasErrors('fixture');
        $this->assertDatabaseCount('fixture_matches', 8);
        $this->get($configure)->assertDontSee('Generar vuelta de la primera fase');

        $user->revokePermissionTo('fixtures.generate');
        $this->post($route)->assertForbidden();
    }

    public function test_new_series_can_append_first_phase_without_changing_existing_matches(): void
    {
        [$company, , $user] = $this->leagueUser(['fixtures.view', 'fixtures.generate']);
        $tournament = $this->tournamentFor($company);
        $route = route('fixtures.generate', ['tournament' => $tournament, 'category' => $tournament->category]);
        $configureRoute = route('fixtures.configure', ['tournament' => $tournament, 'category' => $tournament->category]);

        foreach (['serie_a', 'serie_b'] as $series) {
            foreach (range(1, 2) as $number) {
                TournamentRegistration::factory()->create([
                    'company_id' => $company->id,
                    'tournament_id' => $tournament->id,
                    'division_id' => $tournament->division_id,
                    'category_id' => $tournament->category_id,
                    'team_id' => Team::factory()->create(['company_id' => $company->id])->id,
                    'series' => $series,
                    'team_number' => $number,
                ]);
            }

            if ($series === 'serie_a') {
                $this->actingAs($user)->post($route, ['first_phase_rounds' => 2])->assertSessionHasNoErrors();
                $generation = FixtureGeneration::query()->firstOrFail();
                $match = $generation->matches()->firstOrFail();
                $match->update(['status' => 'completed']);
                $report = MatchReport::query()->create([
                    'company_id' => $company->id,
                    'fixture_match_id' => $match->id,
                    'home_score' => 2,
                    'away_score' => 1,
                    'status' => 'completed',
                ]);
                $existingMatches = $generation->matches()->orderBy('id')->get()->toArray();
                $existingConfig = $generation->config;
                $this->get($configureRoute)->assertDontSee('Generar primera fase de series nuevas');
            }
        }

        $this->get($configureRoute)->assertOk()->assertSee('Generar primera fase de series nuevas');
        $this->post($route, ['first_phase_rounds' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('fixtures.report', $generation));

        $this->assertDatabaseCount('fixture_generations', 1);
        $this->assertSame(4, $generation->refresh()->matches_count);
        $this->assertSame($existingConfig, $generation->config);
        $this->assertSame($existingMatches, $generation->matches()->where('series', 'serie_a')->orderBy('id')->get()->toArray());
        $this->assertSame(2, $generation->matches()->where('series', 'serie_b')->count());
        $this->assertSame([1, 2, 3, 4], $generation->matches()->orderBy('match_number')->pluck('match_number')->all());
        $this->assertSame(0, $generation->matches()->where('phase', '!=', 'group')->count());
        $this->assertDatabaseHas('match_reports', ['id' => $report->id, 'home_score' => 2, 'away_score' => 1]);

        $this->post($route, ['first_phase_rounds' => 2])->assertSessionHasErrors('series');
        $this->assertDatabaseCount('fixture_matches', 4);
        $this->get($configureRoute)->assertDontSee('Generar primera fase de series nuevas');
    }

    public function test_complete_fixture_deletion_removes_played_and_scheduled_matches(): void
    {
        [$company, , $user] = $this->leagueUser(['fixtures.view', 'fixtures.generate']);
        $tournament = $this->tournamentFor($company);
        $matchday = Matchday::factory()->create([
            'company_id' => $company->id,
            'season_id' => $tournament->season_id,
        ]);
        $date = MatchdayDate::factory()->create([
            'company_id' => $company->id,
            'matchday_id' => $matchday->id,
        ]);
        $generation = FixtureGeneration::query()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'category_id' => $tournament->category_id,
            'generated_by' => $user->id,
            'status' => 'active',
            'config' => [],
            'matches_count' => 1,
            'generated_at' => now(),
        ]);
        $match = FixtureMatch::query()->create([
            'company_id' => $company->id,
            'fixture_generation_id' => $generation->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'phase' => 'group',
            'stage' => 'Primera fase',
            'stage_order' => 1,
            'round_number' => 1,
            'match_number' => 1,
            'leg_number' => 1,
            'matchday_date_id' => $date->id,
            'scheduled_time' => '10:00',
            'status' => 'completed',
        ]);
        $report = MatchReport::query()->create([
            'company_id' => $company->id,
            'fixture_match_id' => $match->id,
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'completed',
        ]);

        $this->actingAs($user)
            ->get(route('fixtures.report', $generation))
            ->assertOk()
            ->assertSee('Eliminar todo el fixture');

        $this->actingAs($user)
            ->delete(route('fixtures.destroy', $generation))
            ->assertRedirect(route('fixtures.configure', [
                'tournament' => $tournament,
                'category' => $tournament->category,
            ]));

        $this->assertDatabaseMissing('fixture_generations', ['id' => $generation->id]);
        $this->assertDatabaseMissing('fixture_matches', ['id' => $match->id]);
        $this->assertDatabaseMissing('match_reports', ['id' => $report->id]);
        $this->assertDatabaseHas('matchdays', ['id' => $matchday->id]);
        $this->assertDatabaseHas('matchday_dates', ['id' => $date->id]);
    }

    public function test_series_deletion_removes_its_data_and_preserves_other_series(): void
    {
        [$company, , $user] = $this->leagueUser(['fixtures.view', 'fixtures.generate']);
        $tournament = $this->tournamentFor($company);
        $matchday = Matchday::factory()->create([
            'company_id' => $company->id,
            'season_id' => $tournament->season_id,
        ]);
        $date = MatchdayDate::factory()->create([
            'company_id' => $company->id,
            'matchday_id' => $matchday->id,
        ]);
        $generation = FixtureGeneration::query()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'category_id' => $tournament->category_id,
            'generated_by' => $user->id,
            'status' => 'active',
            'config' => [],
            'matches_count' => 2,
            'generated_at' => now(),
        ]);
        $match = FixtureMatch::query()->create([
            'company_id' => $company->id,
            'fixture_generation_id' => $generation->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $tournament->category_id,
            'phase' => 'group',
            'series' => 'serie_a',
            'stage' => 'Primera fase',
            'stage_order' => 1,
            'round_number' => 1,
            'match_number' => 1,
            'leg_number' => 1,
            'matchday_date_id' => $date->id,
            'scheduled_time' => '10:00',
            'status' => 'completed',
        ]);
        $report = MatchReport::query()->create([
            'company_id' => $company->id,
            'fixture_match_id' => $match->id,
            'home_score' => 2,
            'away_score' => 1,
            'status' => 'completed',
        ]);

        $otherMatch = $match->replicate();
        $otherMatch->fill(['series' => 'serie_b', 'match_number' => 2])->save();
        $otherReport = $report->replicate();
        $otherReport->fixture_match_id = $otherMatch->id;
        $otherReport->save();
        $otherAttributes = $otherMatch->fresh()->getAttributes();
        $secondPhase = $match->replicate();
        $secondPhase->fill(['phase' => 'knockout', 'series' => null, 'match_number' => 3])->save();
        $generation->update(['matches_count' => 3]);

        $this->actingAs($user)
            ->get(route('fixtures.report', $generation))
            ->assertOk()
            ->assertSee('Eliminar fixture de Serie A')
            ->assertSee('Eliminar fixture de Serie B');

        $this->delete(route('fixtures.series.destroy', [$generation, 'invalid']))->assertSessionHasErrors('fixture');
        $this->assertDatabaseCount('fixture_matches', 3);

        $this->actingAs($user)
            ->delete(route('fixtures.series.destroy', [$generation, 'serie_a']))
            ->assertRedirect(route('fixtures.report', $generation));

        $this->assertSame(2, $generation->fresh()->matches_count);
        $this->assertSame($otherAttributes, $otherMatch->fresh()->getAttributes());
        $this->assertDatabaseHas('match_reports', ['id' => $otherReport->id]);
        $this->assertDatabaseHas('fixture_matches', ['id' => $secondPhase->id]);
        $this->get(route('fixtures.report', $generation))->assertOk()->assertDontSee('Eliminar fixture de Serie B');
        $this->delete(route('fixtures.series.destroy', [$generation, 'serie_b']))->assertSessionHasErrors('fixture');
        $this->assertDatabaseHas('fixture_matches', ['id' => $otherMatch->id]);
        $this->assertDatabaseMissing('fixture_matches', ['id' => $match->id]);
        $this->assertDatabaseMissing('match_reports', ['id' => $report->id]);
        $this->assertDatabaseHas('matchdays', ['id' => $matchday->id]);
        $this->assertDatabaseHas('matchday_dates', ['id' => $date->id]);
    }

    public function test_complete_fixture_deletion_requires_generate_permission_and_company_access(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['fixtures.view']);
        $ownTournament = $this->tournamentFor($company);
        $otherTournament = $this->tournamentFor($otherCompany);
        $ownGeneration = FixtureGeneration::query()->create([
            'company_id' => $company->id, 'tournament_id' => $ownTournament->id, 'category_id' => $ownTournament->category_id,
            'generated_by' => $user->id, 'status' => 'active', 'config' => [], 'matches_count' => 0, 'generated_at' => now(),
        ]);
        $otherGeneration = FixtureGeneration::query()->create([
            'company_id' => $otherCompany->id, 'tournament_id' => $otherTournament->id, 'category_id' => $otherTournament->category_id,
            'status' => 'active', 'config' => [], 'matches_count' => 0, 'generated_at' => now(),
        ]);

        $this->actingAs($user)->delete(route('fixtures.destroy', $ownGeneration))->assertForbidden();
        $this->delete(route('fixtures.series.destroy', [$ownGeneration, 'serie_a']))->assertForbidden();

        Permission::findOrCreate('fixtures.generate');
        $user->givePermissionTo('fixtures.generate');

        $this->actingAs($user)->delete(route('fixtures.destroy', $otherGeneration))->assertForbidden();
        $this->delete(route('fixtures.series.destroy', [$otherGeneration, 'serie_a']))->assertForbidden();
        $this->assertDatabaseHas('fixture_generations', ['id' => $otherGeneration->id]);
    }

    private function tournamentFor(Company $company): Tournament
    {
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'name' => 'Mayores',
        ]);

        return Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'name' => 'Torneo '.$company->id,
            'status' => 'active',
        ]);
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create(['name' => 'Liga Propia']);
        $otherCompany = Company::factory()->create(['name' => 'Liga Ajena']);
        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }
}
