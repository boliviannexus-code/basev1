<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Division;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerRosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_import_players_teams_and_roster_affiliations_from_csv(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.view', 'player-imports.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,123ABC,2000-02-10,Macanudos FC,Mayores',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'))
            ->assertSessionHas('import_report');

        $team = Team::query()->where('name', 'MACANUDOS FC')->firstOrFail();
        $player = Player::query()->where('ci_normalized', Player::normalizeCi('123ABC'))->firstOrFail();

        $this->assertSame($company->id, $team->company_id);
        $this->assertSame($company->id, $player->company_id);
        $this->assertDatabaseHas('team_players', [
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
            'status' => TeamPlayer::STATUS_ACTIVE,
        ]);
    }

    public function test_import_reuses_existing_team_by_strong_match_and_existing_player_by_ci(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);
        $team = Team::factory()->create(['company_id' => $company->id, 'name' => 'Macanudos FC']);
        $player = Player::factory()->create([
            'company_id' => $company->id,
            'ci' => '777ABC',
            'ci_normalized' => Player::normalizeCi('777ABC'),
            'birth_date' => '1999-05-02',
        ]);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,777-abc,1999-05-02,Macanudos,Mayores',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'));

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseHas('team_players', [
            'division_id' => $division->id,
            'team_id' => $team->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_import_creates_repeated_team_only_once_for_multiple_players_in_same_file(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,123ABC,2000-02-10,ADS,Mayores',
            'Pedro,Quispe,Mamani,456ABC,2001-03-11,ADS,Mayores',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'));

        $team = Team::query()->where('name', 'ADS')->firstOrFail();

        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('players', 2);
        $this->assertDatabaseCount('team_players', 2);
        $this->assertDatabaseHas('team_players', [
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $team->id,
        ]);
    }

    public function test_import_uses_last_row_when_player_ci_is_repeated_inside_same_file(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.create']);
        Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,123ABC,2000-02-10,ADS,Mayores',
            'Pedro,Quispe,Mamani,123-abc,2001-03-11,ADS,Mayores',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'));

        $report = session('import_report');
        $player = Player::query()->where('ci_normalized', Player::normalizeCi('123ABC'))->firstOrFail();

        $this->assertSame(1, $report['summary']['imported_rows']);
        $this->assertSame(0, $report['summary']['failed_rows']);
        $this->assertSame(1, $report['summary']['duplicate_rows_ignored']);
        $this->assertSame('ignored', $report['rows'][0]['status']);
        $this->assertSame('imported', $report['rows'][1]['status']);
        $this->assertSame('Pedro', $player->first_name);
        $this->assertSame('Quispe', $player->last_name);
        $this->assertSame('Mamani', $player->maternal_name);
        $this->assertDatabaseCount('teams', 1);
        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_import_skips_player_that_already_belongs_to_another_team_in_same_division(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.create']);
        $division = Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo A']);
        $player = Player::factory()->create([
            'company_id' => $company->id,
            'ci' => '888ABC',
            'ci_normalized' => Player::normalizeCi('888ABC'),
            'birth_date' => '1998-01-01',
        ]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $division->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,888ABC,1998-01-01,Equipo B,Mayores',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'));

        $report = session('import_report');

        $this->assertSame(1, $report['summary']['failed_rows']);
        $this->assertDatabaseMissing('teams', [
            'company_id' => $company->id,
            'name' => 'EQUIPO B',
        ]);
        $this->assertDatabaseCount('team_players', 1);
    }

    public function test_import_allows_same_player_in_another_division_with_another_team(): void
    {
        [$company, $user] = $this->leagueUser(['player-imports.create']);
        $firstDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Mayores']);
        $secondDivision = Division::factory()->create(['company_id' => $company->id, 'name' => 'Senior']);
        $firstTeam = Team::factory()->create(['company_id' => $company->id, 'name' => 'Equipo A']);
        $player = Player::factory()->create([
            'company_id' => $company->id,
            'ci' => '999ABC',
            'ci_normalized' => Player::normalizeCi('999ABC'),
            'birth_date' => '1980-01-01',
        ]);

        TeamPlayer::factory()->create([
            'company_id' => $company->id,
            'division_id' => $firstDivision->id,
            'team_id' => $firstTeam->id,
            'player_id' => $player->id,
        ]);

        $file = UploadedFile::fake()->createWithContent('jugadores.csv', implode("\n", [
            'nombre,paterno,materno,ci,fecha de nacimiento,equipo,division',
            'Juan,Perez,Rojas,999ABC,1980-01-01,Equipo B,Senior',
        ]));

        $this
            ->actingAs($user)
            ->post(route('player-imports.store'), [
                'file' => $file,
            ])
            ->assertRedirect(route('player-imports.index'));

        $secondTeam = Team::query()->where('name', 'EQUIPO B')->firstOrFail();

        $this->assertDatabaseHas('team_players', [
            'division_id' => $secondDivision->id,
            'team_id' => $secondTeam->id,
            'player_id' => $player->id,
        ]);
        $this->assertDatabaseCount('team_players', 2);
    }

    private function leagueUser(array $permissions): array
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo($permissions);

        return [$company, $user];
    }
}
