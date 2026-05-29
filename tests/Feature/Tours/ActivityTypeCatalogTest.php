<?php

namespace Tests\Feature\Tours;

use App\Models\ActivityType;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ActivityTypeCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_activity_types(): void
    {
        $user = User::factory()->create();
        $permissions = [
            'activity_types.view',
            'activity_types.create',
            'activity_types.update',
            'activity_types.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user->givePermissionTo($permissions);

        $this
            ->actingAs($user)
            ->get(route('activity-types.index'))
            ->assertOk()
            ->assertSee('Tipos de actividad');

        $this
            ->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('activity-types.store'), [
                'title' => 'Observacion',
                'slug' => '',
                'icon' => 'ti-binoculars',
                'description' => 'Actividad de observacion.',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        $activityType = ActivityType::query()->where('slug', 'observacion')->firstOrFail();

        $this
            ->actingAs($user)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->putJson(route('activity-types.update', $activityType), [
                'title' => 'Observacion guiada',
                'slug' => 'observacion-guiada',
                'icon' => 'ti-binoculars',
                'description' => 'Actividad actualizada.',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('activity_types', [
            'id' => $activityType->id,
            'title' => 'Observacion guiada',
            'slug' => 'observacion-guiada',
            'is_active' => false,
        ]);

        $this
            ->actingAs($user)
            ->delete(route('activity-types.destroy', $activityType))
            ->assertRedirect(route('activity-types.index'));

        $this->assertDatabaseMissing('activity_types', [
            'id' => $activityType->id,
        ]);
    }

    public function test_activity_type_seeder_creates_itinerary_base_catalog(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        foreach (['transporte', 'comida', 'caminata', 'visita', 'descanso', 'alojamiento'] as $slug) {
            $this->assertDatabaseHas('activity_types', [
                'slug' => $slug,
                'is_active' => true,
            ]);
        }
    }
}
