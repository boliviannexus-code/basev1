<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PlayerPhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_photo_is_optimized_as_square_webp_and_original_is_not_stored(): void
    {
        Storage::fake('public');
        [$company, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('players.photo.update', $player), [
                'photo' => UploadedFile::fake()->image('player.jpg', 1200, 800)->size(2048),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['photo_data_uri']]);

        $player->refresh();

        $this->assertSame('players/photos/'.$player->internal_code.'.webp', $player->photo_path);
        Storage::disk('public')->assertExists($player->photo_path);
        Storage::disk('public')->assertMissing('players/photos/player.jpg');

        $absolutePath = Storage::disk('public')->path($player->photo_path);
        $size = getimagesize($absolutePath);

        $this->assertSame(600, $size[0]);
        $this->assertSame(600, $size[1]);
        $this->assertSame(IMAGETYPE_WEBP, $size[2]);
        $this->assertLessThan(120 * 1024, filesize($absolutePath));
    }

    public function test_player_show_modal_embeds_saved_photo_without_public_storage_url(): void
    {
        Storage::fake('public');
        [, $user] = $this->leagueUser(['players.view', 'players.update']);
        $player = Player::factory()->create();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('players.photo.update', $player), [
                'photo' => UploadedFile::fake()->image('player.jpg', 900, 900)->size(1024),
            ])
            ->assertOk();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('players.show', $player))
            ->assertOk()
            ->assertSee('data:image/webp;base64', false)
            ->assertDontSee('/storage/players/photos/'.$player->refresh()->internal_code.'.webp', false);
    }

    public function test_player_photo_form_is_loaded_as_modal_content(): void
    {
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('players.photo.edit', $player))
            ->assertOk()
            ->assertSee('data-player-photo-form', false)
            ->assertSee('data-player-photo-cropper', false)
            ->assertSee('data-player-photo-canvas', false)
            ->assertSee('data-player-photo-zoom', false)
            ->assertSee('Guardar foto')
            ->assertSee('Salida: 600x600 WebP');
    }

    public function test_replacing_player_photo_deletes_previous_file_when_path_changes(): void
    {
        Storage::fake('public');
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create(['photo_path' => 'players/photos/old-photo.webp']);
        Storage::disk('public')->put($player->photo_path, 'old');

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('players.photo.update', $player), [
                'photo' => UploadedFile::fake()->image('player.png', 900, 1200)->size(3072),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing('players/photos/old-photo.webp');
        Storage::disk('public')->assertExists($player->refresh()->photo_path);
    }

    public function test_player_photo_requires_valid_image_file(): void
    {
        Storage::fake('public');
        [, $user] = $this->leagueUser(['players.update']);
        $player = Player::factory()->create();

        $this
            ->actingAs($user)
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post(route('players.photo.update', $player), [
                'photo' => UploadedFile::fake()->create('broken.txt', 12, 'text/plain'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
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
