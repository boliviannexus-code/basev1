<?php

namespace Tests\Feature\Leagues;

use App\Models\BiometricFingerprint;
use App\Models\Company;
use App\Models\Player;
use App\Models\User;
use App\Services\Biometrics\BiometricEngineClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerBiometricRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_open_player_biometric_registration_form(): void
    {
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('players.biometric-registration.create', $player))
            ->assertOk()
            ->assertSee('Indice derecho')
            ->assertSee('Detectar lector')
            ->assertSee('Capturar huella')
            ->assertSee('Guardar');
    }

    public function test_player_right_index_fingerprint_can_be_registered(): void
    {
        [$company, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();
        $this->mockTemplateCreation();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('players.biometric-registration.store', $player), [
                'sample_image' => $this->sampleImage(),
                'quality_score' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.finger_position', 'right_index');

        $this->assertDatabaseHas('biometric_fingerprints', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'player_id' => $player->id,
            'finger_position' => 'right_index',
            'format' => 'png_base64',
            'template_format' => 'sourceafis_test',
            'quality_score' => 100,
            'is_active' => true,
        ]);
    }

    public function test_client_sent_finger_position_is_ignored(): void
    {
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();
        $this->mockTemplateCreation();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('players.biometric-registration.store', $player), [
                'sample_image' => $this->sampleImage(),
                'finger_position' => 'left_index',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('biometric_fingerprints', [
            'player_id' => $player->id,
            'finger_position' => 'right_index',
        ]);

        $this->assertDatabaseMissing('biometric_fingerprints', [
            'player_id' => $player->id,
            'finger_position' => 'left_index',
        ]);
    }

    public function test_existing_active_right_index_blocks_new_registration(): void
    {
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        BiometricFingerprint::query()->create([
            'user_id' => $user->id,
            'player_id' => $player->id,
            'finger_position' => 'right_index',
            'sample_image' => encrypt($this->sampleImage()),
            'template_data' => encrypt('stored-template'),
            'template_format' => 'sourceafis_test',
            'format' => 'png_base64',
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        $this->mock(BiometricEngineClient::class)
            ->shouldNotReceive('createTemplate');

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('players.biometric-registration.store', $player), [
                'sample_image' => $this->sampleImage(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame(1, BiometricFingerprint::query()->where('player_id', $player->id)->count());
    }

    public function test_user_without_permission_cannot_open_or_store_player_biometric_registration(): void
    {
        [, $user] = $this->leagueUser([]);
        $player = Player::factory()->create();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('players.biometric-registration.create', $player))
            ->assertForbidden();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('players.biometric-registration.store', $player), [
                'sample_image' => $this->sampleImage(),
            ])
            ->assertForbidden();
    }

    public function test_biometric_engine_error_returns_json_without_creating_fingerprint(): void
    {
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        $this->mock(BiometricEngineClient::class)
            ->shouldReceive('createTemplate')
            ->once()
            ->andThrow(new RuntimeException('engine down'));

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->postJson(route('players.biometric-registration.store', $player), [
                'sample_image' => $this->sampleImage(),
            ])
            ->assertStatus(502)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('biometric_fingerprints', [
            'player_id' => $player->id,
        ]);
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

    private function mockTemplateCreation(): void
    {
        $this->mock(BiometricEngineClient::class)
            ->shouldReceive('createTemplate')
            ->once()
            ->andReturn([
                'template' => 'template-data',
                'format' => 'sourceafis_test',
            ]);
    }

    private function sampleImage(): string
    {
        return str_repeat('a', 128);
    }
}
