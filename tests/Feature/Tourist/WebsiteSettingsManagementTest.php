<?php

namespace Tests\Feature\Tourist;

use App\Models\Category;
use App\Models\Company;
use App\Models\Tour;
use App\Models\TourPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WebsiteSettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_home_content_and_featured_tours(): void
    {
        $user = $this->userWithPermission('website.manage');
        $tour = $this->bookableTour(['title' => 'Tour destacado especial']);

        $this->actingAs($user)
            ->put(route('website-settings.update'), [
                'hero_eyebrow' => 'Oferta de temporada',
                'hero_title' => 'Explora Bolivia',
                'hero_subtitle' => 'Tours seleccionados por expertos locales.',
                'popup_enabled' => '1',
                'popup_title' => 'Promo Uyuni',
                'popup_body' => 'Reserva hoy y recibe soporte personalizado.',
                'popup_cta_label' => 'Ver oferta',
                'popup_cta_url' => '/tours',
                'featured_tour_ids' => [$tour->id],
                'show_companies' => '1',
            ])
            ->assertRedirect(route('website-settings.edit'));

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Explora Bolivia')
            ->assertSee('Promo Uyuni')
            ->assertSee('Tour destacado especial');
    }

    public function test_website_content_api_returns_home_configuration_for_flutter(): void
    {
        $user = $this->userWithPermission('website.manage');
        $tour = $this->bookableTour(['title' => 'API destacado']);

        $this->actingAs($user)->put(route('website-settings.update'), [
            'hero_title' => 'Home API',
            'featured_tour_ids' => [$tour->id],
            'show_companies' => '1',
        ]);

        $this->getJson(route('api.v1.website-content'))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hero.title', 'Home API')
            ->assertJsonPath('data.featured_tours.0.title', 'API destacado');
    }

    public function test_user_without_permission_cannot_manage_website_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('website-settings.edit'))
            ->assertForbidden();
    }

    private function userWithPermission(string $permission): User
    {
        Permission::findOrCreate($permission);
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        return $user;
    }

    private function bookableTour(array $overrides = []): Tour
    {
        $company = Company::factory()->create();
        $category = Category::factory()->create();

        $tour = Tour::query()->create(array_merge([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'name' => 'Tour publico',
            'title' => 'Tour publico',
            'description' => 'Descripcion del tour',
            'city' => 'La Paz',
            'country' => 'Bolivia',
            'duration' => '1 dia',
            'status' => Tour::STATUS_ACTIVE,
            'review_status' => Tour::REVIEW_APPROVED,
            'bookings_enabled' => true,
        ], $overrides));

        TourPrice::query()->create([
            'tour_id' => $tour->id,
            'title' => 'Adulto',
            'min_people' => 1,
            'max_people' => null,
            'price_usd' => 20,
        ]);

        return $tour;
    }
}
