<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Court;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use App\Models\Season;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MatchdayTest extends TestCase
{
    use RefreshDatabase;

    public function test_matchdays_start_from_active_seasons_and_create_incremental_numbers_per_season(): void
    {
        [$company, $otherCompany, $user] = $this->leagueUser(['matchdays.view', 'matchdays.create', 'matchdays.update']);
        $season = Season::factory()->create([
            'company_id' => $company->id,
            'name' => 'Gestion 2026',
            'year' => 2026,
            'status' => 'active',
            'is_active' => true,
        ]);
        $secondSeason = Season::factory()->create([
            'company_id' => $company->id,
            'name' => 'Gestion 2027',
            'year' => 2027,
            'status' => 'active',
            'is_active' => true,
        ]);
        $closedSeason = Season::factory()->create([
            'company_id' => $company->id,
            'name' => 'Gestion cerrada',
            'status' => 'closed',
            'is_active' => false,
        ]);
        $otherSeason = Season::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Gestion ajena',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.index'))
            ->assertOk()
            ->assertSee('Jornadas')
            ->assertSee('Gestion 2026')
            ->assertSee('Gestion 2027')
            ->assertDontSee('Gestion cerrada')
            ->assertDontSee('Gestion ajena');

        $this
            ->actingAs($user)
            ->post(route('matchdays.store', $season))
            ->assertRedirect(route('matchdays.show', $season));

        $this->assertDatabaseHas('matchdays', [
            'company_id' => $company->id,
            'season_id' => $season->id,
            'number' => 1,
            'name' => 'Jornada 1',
        ]);

        $this
            ->actingAs($user)
            ->post(route('matchdays.store', $season))
            ->assertRedirect(route('matchdays.show', $season));

        $this
            ->actingAs($user)
            ->post(route('matchdays.store', $secondSeason))
            ->assertRedirect(route('matchdays.show', $secondSeason));

        $this->assertDatabaseHas('matchdays', [
            'season_id' => $season->id,
            'number' => 2,
            'name' => 'Jornada 2',
        ]);
        $this->assertDatabaseHas('matchdays', [
            'season_id' => $secondSeason->id,
            'number' => 1,
            'name' => 'Jornada 1',
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.show', $season))
            ->assertOk()
            ->assertSee('Jornada 1')
            ->assertSee('Jornada 2')
            ->assertSee('Configurar');

        $matchday = Matchday::query()
            ->where('season_id', $season->id)
            ->where('number', 1)
            ->firstOrFail();
        $court = Court::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cancha Central',
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.configure', $matchday))
            ->assertOk()
            ->assertSee('Agregar fechas')
            ->assertSee('Cancha Central')
            ->assertSee('Fechas de la jornada');

        $this
            ->actingAs($user)
            ->post(route('matchdays.dates.store', $matchday), [
                'court_id' => $court->id,
                'date' => '2026-08-01',
            ])
            ->assertRedirect(route('matchdays.configure', $matchday));

        $this->assertSame(1, MatchdayDate::query()->where('matchday_id', $matchday->id)->count());
        $this->assertDatabaseHas('matchday_dates', [
            'matchday_id' => $matchday->id,
            'court_id' => $court->id,
            'date' => '2026-08-01',
        ]);

        $this
            ->actingAs($user)
            ->from(route('matchdays.configure', $matchday))
            ->post(route('matchdays.dates.store', $matchday), [
                'court_id' => $court->id,
                'date' => '2026-08-01',
            ])
            ->assertRedirect(route('matchdays.configure', $matchday))
            ->assertSessionHasErrors([
                'date' => 'Ya existe esa fecha con la misma cancha en esta jornada.',
            ]);

        $this->assertSame(3, Matchday::query()->count());
        $this
            ->actingAs($user)
            ->get(route('matchdays.show', $otherSeason))
            ->assertForbidden();
    }

    public function test_matchdays_require_permission(): void
    {
        Permission::findOrCreate('matchdays.view');
        [$company, , $user] = $this->leagueUser([]);
        $season = Season::factory()->create(['company_id' => $company->id]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.show', $season))
            ->assertForbidden();
    }

    public function test_finalized_matchday_can_be_reopened_to_add_matches(): void
    {
        [$company, , $user] = $this->leagueUser(['matchdays.view', 'matchdays.update']);
        $season = Season::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $matchday = Matchday::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'status' => 'finalized',
        ]);

        $this
            ->actingAs($user)
            ->patch(route('matchdays.reopen', $matchday))
            ->assertRedirect(route('matchdays.configure', $matchday));

        $this->assertDatabaseHas('matchdays', [
            'id' => $matchday->id,
            'status' => 'draft',
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.configure', $matchday))
            ->assertOk()
            ->assertSee('Agregar fechas')
            ->assertSee('Finalizar');
    }

    public function test_matchday_date_can_schedule_fixture_matches_by_tournament_category_and_series(): void
    {
        [$company, , $user] = $this->leagueUser(['matchdays.view', 'matchdays.create', 'matchdays.update']);
        $season = Season::factory()->create(['company_id' => $company->id]);
        $division = Division::factory()->create(['company_id' => $company->id]);
        $category = DivisionCategory::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'name' => 'Mayores',
        ]);
        $tournament = Tournament::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'status' => 'active',
        ]);
        $generation = FixtureGeneration::query()->create([
            'company_id' => $company->id,
            'tournament_id' => $tournament->id,
            'category_id' => $category->id,
            'generated_by' => $user->id,
            'status' => 'active',
            'config' => [],
            'matches_count' => 1,
            'generated_at' => now(),
        ]);
        $home = Team::factory()->create(['company_id' => $company->id, 'name' => 'LOCAL']);
        $away = Team::factory()->create(['company_id' => $company->id, 'name' => 'VISITANTE']);
        $match = FixtureMatch::query()->create([
            'company_id' => $company->id,
            'fixture_generation_id' => $generation->id,
            'tournament_id' => $tournament->id,
            'division_id' => $division->id,
            'category_id' => $category->id,
            'phase' => 'group',
            'stage' => 'Primera fase',
            'stage_order' => 1,
            'series' => 'serie_a',
            'round_number' => 1,
            'match_number' => 1,
            'leg_number' => 1,
            'home_team_id' => $home->id,
            'away_team_id' => $away->id,
            'status' => 'pending_schedule',
        ]);
        $matchday = Matchday::factory()->create([
            'company_id' => $company->id,
            'season_id' => $season->id,
            'number' => 1,
            'name' => 'Jornada 1',
        ]);
        $court = Court::factory()->create([
            'company_id' => $company->id,
            'name' => 'Cancha Norte',
        ]);
        $date = MatchdayDate::factory()->create([
            'company_id' => $company->id,
            'matchday_id' => $matchday->id,
            'court_id' => $court->id,
            'date' => '2026-08-01',
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.dates.configure', [
                'date' => $date,
                'tournament_id' => $tournament->id,
                'fixture_group' => $category->id.'|serie_a',
            ]))
            ->assertOk()
            ->assertSee('Mayores - Serie A')
            ->assertSee('LOCAL')
            ->assertSee('VISITANTE')
            ->assertSee('Programar');

        $this
            ->actingAs($user)
            ->post(route('matchdays.dates.matches.store', [
                'date' => $date,
                'tournament_id' => $tournament->id,
                'category_id' => $category->id,
                'series' => 'serie_a',
            ]), [
                'fixture_match_id' => $match->id,
                'scheduled_time' => '19:30',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('fixture_matches', [
            'id' => $match->id,
            'matchday_date_id' => $date->id,
            'status' => 'scheduled',
        ]);

        $this
            ->actingAs($user)
            ->get(route('matchdays.show', $season))
            ->assertOk()
            ->assertSee('1 partido(s)');

        $this
            ->actingAs($user)
            ->get(route('matchdays.dates.configure', $date))
            ->assertOk()
            ->assertSee('19:30')
            ->assertSee('LOCAL')
            ->assertSee('VISITANTE');

        $this
            ->actingAs($user)
            ->patch(route('matchdays.dates.matches.time', [
                'date' => $date,
                'fixtureMatch' => $match,
            ]), [
                'scheduled_time' => '18:15',
            ])
            ->assertRedirect(route('matchdays.dates.configure', $date));

        $this->assertDatabaseHas('fixture_matches', [
            'id' => $match->id,
            'scheduled_time' => '18:15:00',
        ]);

        $this
            ->actingAs($user)
            ->delete(route('matchdays.dates.matches.destroy', [
                'date' => $date,
                'fixtureMatch' => $match,
            ]))
            ->assertRedirect(route('matchdays.dates.configure', $date));

        $this->assertDatabaseHas('fixture_matches', [
            'id' => $match->id,
            'matchday_date_id' => null,
            'scheduled_time' => null,
            'status' => 'pending_schedule',
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
