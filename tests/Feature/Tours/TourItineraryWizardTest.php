<?php

namespace Tests\Feature\Tours;

use App\Models\ActivityType;
use App\Models\Company;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TourItineraryWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_tour_itinerary_step(): void
    {
        Permission::findOrCreate('tours.edit');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('tours.edit');
        $user->setRelation('company', $company);

        $activityType = ActivityType::query()->create([
            'title' => 'Visita',
            'slug' => 'visita',
            'icon' => 'ti-map-pin',
            'description' => 'Visita turistica.',
            'is_active' => true,
        ]);

        $tour = Tour::query()->create([
            'company_id' => $company->id,
            'name' => 'Tour Demo',
            'title' => 'Tour Demo',
            'description' => 'Demo',
            'status' => Tour::STATUS_DRAFT,
            'review_status' => Tour::REVIEW_DRAFT,
            'current_step' => 10,
        ]);

        $this
            ->actingAs($user)
            ->get(route('tours.wizard.edit', [$tour, 'step' => 10]))
            ->assertOk()
            ->assertSee('Itinerario dia a dia');

        $this
            ->actingAs($user)
            ->patch(route('tours.wizard.step', [$tour, 10]), [
                'action' => 'draft',
                'itinerary_days' => [[
                    'day_number' => 1,
                    'title' => 'Llegada y city tour',
                    'summary' => 'Primer dia del recorrido.',
                    'stops' => [[
                        'activity_type_id' => $activityType->id,
                        'position' => 1,
                        'start_time' => '08:30',
                        'title' => 'Visita al centro historico',
                        'location_name' => 'Centro',
                    ]],
                ]],
            ])
            ->assertRedirect(route('tours.wizard.edit', [$tour, 'step' => 10]));

        $this->assertDatabaseHas('tour_itinerary_days', [
            'tour_id' => $tour->id,
            'day_number' => 1,
            'title' => 'Llegada y city tour',
        ]);
        $this->assertDatabaseHas('tour_itinerary_stops', [
            'activity_type_id' => $activityType->id,
            'position' => 1,
            'title' => 'Visita al centro historico',
            'location_name' => 'Centro',
        ]);
    }

    public function test_tour_image_step_rejects_images_over_the_size_limit(): void
    {
        Storage::fake('public');
        Permission::findOrCreate('tours.edit');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('tours.edit');
        $user->setRelation('company', $company);

        $tour = Tour::query()->create([
            'company_id' => $company->id,
            'name' => 'Tour con imagen pesada',
            'title' => 'Tour con imagen pesada',
            'description' => 'Demo',
            'status' => Tour::STATUS_DRAFT,
            'review_status' => Tour::REVIEW_DRAFT,
            'current_step' => 8,
        ]);

        $this
            ->actingAs($user)
            ->from(route('tours.wizard.edit', [$tour, 'step' => 8]))
            ->patch(route('tours.wizard.step', [$tour, 8]), [
                'action' => 'draft',
                'images' => [
                    UploadedFile::fake()->create('imagen-pesada.jpg', 4097, 'image/jpeg'),
                ],
            ])
            ->assertRedirect(route('tours.wizard.edit', [$tour, 'step' => 8]))
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseMissing('tour_images', [
            'tour_id' => $tour->id,
        ]);
    }
}
