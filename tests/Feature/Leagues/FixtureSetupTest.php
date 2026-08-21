<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
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
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$company, $otherCompany, $user];
    }
}
