<?php

namespace Tests\Feature\FingerprintTemplates;

use App\Models\Company;
use App\Models\FingerprintTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FingerprintTemplateCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_fingerprint_templates_for_own_league_users(): void
    {
        [$company, , $actor] = $this->leagueUser([
            'fingerprint-templates.view',
            'fingerprint-templates.create',
            'fingerprint-templates.update',
            'fingerprint-templates.delete',
        ]);
        $user = User::factory()->create(['company_id' => $company->id]);

        $this
            ->actingAs($actor)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('fingerprint-templates.store'), [
                'user_id' => $user->id,
                'format' => 'ISO',
                'template_data' => str_repeat('A', 32),
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $template = FingerprintTemplate::query()->firstOrFail();

        $this
            ->actingAs($actor)
            ->get(route('fingerprint-templates.index'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee(str_repeat('A', 32));

        $this
            ->actingAs($actor)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('fingerprint-templates.update', $template), [
                'format' => 'ANSI',
                'template_data' => str_repeat('B', 32),
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('fingerprint_templates', [
            'id' => $template->id,
            'format' => 'ANSI',
            'template_data' => str_repeat('B', 32),
        ]);

        $this
            ->actingAs($actor)
            ->delete(route('fingerprint-templates.destroy', $template))
            ->assertRedirect(route('fingerprint-templates.index'));

        $this->assertDatabaseMissing('fingerprint_templates', [
            'id' => $template->id,
        ]);
    }

    public function test_user_cannot_register_fingerprint_for_other_league_user(): void
    {
        [, $otherCompany, $actor] = $this->leagueUser(['fingerprint-templates.create']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);

        $this
            ->actingAs($actor)
            ->post(route('fingerprint-templates.store'), [
                'user_id' => $otherUser->id,
                'format' => 'ISO',
                'template_data' => str_repeat('A', 32),
            ])
            ->assertSessionHasErrors('user_id');
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
}
