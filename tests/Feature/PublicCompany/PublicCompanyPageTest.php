<?php

namespace Tests\Feature\PublicCompany;

use App\Models\AccommodationPackage;
use App\Models\Company;
use App\Models\PackageService;
use App\Models\Space;
use App\Models\SpaceLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicCompanyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_company_page_is_visible_when_admin_and_company_flags_are_enabled(): void
    {
        $company = Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'public_name' => 'Nexus Travel',
            'public_description' => 'Viajes y estadías seleccionadas.',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);

        $visiblePackage = $this->package($company, [
            'name' => 'Escapada romántica',
            'is_active' => true,
        ]);
        $visibleSpace = $this->spaceWithLocation($company, [
            'title' => 'Nexus Lake Lodge',
            'status' => 'active',
            'city' => 'Copacabana',
        ]);
        $visiblePackage->spaces()->attach($visibleSpace->id);

        $this->spaceWithLocation(Company::factory()->create(), [
            'title' => 'Otro Lodge',
            'status' => 'active',
            'city' => 'Sucre',
        ]);

        $this->spaceWithLocation($company, [
            'title' => 'Nexus Inactivo',
            'status' => 'inactive',
            'city' => 'Tarija',
        ]);

        $this->package(Company::factory()->create(), [
            'name' => 'Paquete de otra empresa',
            'is_active' => true,
        ]);

        $this->package($company, [
            'name' => 'Paquete inactivo',
            'is_active' => false,
        ]);

        $this
            ->get(route('public.company.show', 'nexus-travel'))
            ->assertOk()
            ->assertSee('Nexus Travel')
            ->assertSee('Viajes y estadías seleccionadas.')
            ->assertSee($visiblePackage->name)
            ->assertSee($visibleSpace->title)
            ->assertSee('Espacio')
            ->assertSee('Copacabana')
            ->assertSee('data-public-company-location', false)
            ->assertSee('data-location-id="space-'.$visibleSpace->id.'"', false)
            ->assertSee('"id":"space-'.$visibleSpace->id.'"', false)
            ->assertDontSee('Otro Lodge')
            ->assertDontSee('Nexus Inactivo')
            ->assertDontSee('Paquete de otra empresa')
            ->assertDontSee('Paquete inactivo');
    }

    public function test_home_lists_only_companies_with_public_pages_online(): void
    {
        Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'public_name' => 'Nexus Travel',
            'public_description' => 'Empresa visible en línea.',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);

        Company::factory()->create([
            'public_slug' => 'admin-disabled',
            'public_name' => 'Admin Disabled Travel',
            'is_online_enabled_by_admin' => false,
            'is_public_enabled' => true,
        ]);

        Company::factory()->create([
            'public_slug' => 'company-disabled',
            'public_name' => 'Company Disabled Travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => false,
        ]);

        Company::factory()->create([
            'public_slug' => null,
            'public_name' => 'No Slug Travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);

        $this
            ->get(route('public.accommodations.index'))
            ->assertOk()
            ->assertSee('Empresas en línea')
            ->assertSee('Nexus Travel')
            ->assertSee('Empresa visible en línea.')
            ->assertDontSee('Admin Disabled Travel')
            ->assertDontSee('Company Disabled Travel')
            ->assertDontSee('No Slug Travel');
    }

    public function test_public_company_page_returns_404_when_admin_has_not_enabled_online_status(): void
    {
        Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'is_online_enabled_by_admin' => false,
            'is_public_enabled' => true,
        ]);

        $this
            ->get(route('public.company.show', 'nexus-travel'))
            ->assertNotFound();
    }

    public function test_public_company_page_returns_404_when_company_has_disabled_public_page(): void
    {
        Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => false,
        ]);

        $this
            ->get(route('public.company.show', 'nexus-travel'))
            ->assertNotFound();
    }

    public function test_public_package_detail_is_visible_for_active_package_owned_by_visible_company(): void
    {
        $company = Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'public_name' => 'Nexus Travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);
        $package = $this->package($company, [
            'name' => 'Isla del Sol Full Day',
            'slug' => 'isla-del-sol-full-day',
            'is_active' => true,
        ]);
        $space = $this->spaceWithLocation($company, [
            'title' => 'Lodge Isla del Sol',
            'city' => 'Copacabana',
        ]);
        $package->spaces()->attach($space->id);
        $service = PackageService::query()->create([
            'company_id' => $company->id,
            'name' => 'Paseo en bote',
            'description' => 'Transporte lacustre',
            'type' => 'tour',
            'icon' => 'ti ti-sailboat',
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $package->services()->attach($service->id, [
            'inclusion_type' => 'included',
            'custom_description' => 'Salida desde el muelle principal.',
            'sort_order' => 0,
        ]);

        $this
            ->get(route('public.company.packages.show', [$company->public_slug, $package->slug]))
            ->assertOk()
            ->assertSee('Isla del Sol Full Day')
            ->assertSee('Nexus Travel')
            ->assertSee('Lodge Isla del Sol')
            ->assertSee('data-public-company-map', false)
            ->assertSee('"id":"space-'.$space->id.'"', false)
            ->assertSee('ti ti-sailboat', false)
            ->assertSee('Salida desde el muelle principal.')
            ->assertSee('Consultar por WhatsApp');
    }

    public function test_public_package_detail_returns_404_for_package_from_another_company(): void
    {
        $company = Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);
        $otherCompany = Company::factory()->create([
            'public_slug' => 'other-travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);
        $package = $this->package($otherCompany, [
            'slug' => 'isla-del-sol-full-day',
            'is_active' => true,
        ]);

        $this
            ->get(route('public.company.packages.show', [$company->public_slug, $package->slug]))
            ->assertNotFound();
    }

    public function test_public_package_detail_returns_404_for_inactive_package(): void
    {
        $company = Company::factory()->create([
            'public_slug' => 'nexus-travel',
            'is_online_enabled_by_admin' => true,
            'is_public_enabled' => true,
        ]);
        $package = $this->package($company, [
            'slug' => 'isla-del-sol-full-day',
            'is_active' => false,
        ]);

        $this
            ->get(route('public.company.packages.show', [$company->public_slug, $package->slug]))
            ->assertNotFound();
    }

    private function package(Company $company, array $overrides = []): AccommodationPackage
    {
        return AccommodationPackage::query()->create([
            'company_id' => $company->id,
            'name' => $overrides['name'] ?? 'Paquete público',
            'slug' => $overrides['slug'] ?? Str::slug($overrides['name'] ?? 'Paquete público'),
            'short_description' => 'Descripción corta del paquete.',
            'price' => 600,
            'currency' => 'BOB',
            'included_people' => 2,
            'requires_full_private_space' => true,
            'nights_included' => 1,
            'is_active' => $overrides['is_active'] ?? true,
            'is_featured' => false,
            'sort_order' => 0,
        ]);
    }

    private function spaceWithLocation(Company $company, array $overrides = []): Space
    {
        $space = Space::factory()->create([
            'company_id' => $company->id,
            'title' => $overrides['title'] ?? 'Espacio público',
            'name' => $overrides['title'] ?? 'Espacio público',
            'status' => $overrides['status'] ?? 'active',
        ]);

        SpaceLocation::query()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'country' => $overrides['country'] ?? 'Bolivia',
            'city' => $overrides['city'] ?? 'La Paz',
            'address' => $overrides['address'] ?? 'Av. Principal',
            'address_text' => $overrides['address'] ?? 'Av. Principal',
            'reference' => $overrides['reference'] ?? 'Frente a la plaza',
            'reference_text' => $overrides['reference'] ?? 'Frente a la plaza',
            'latitude' => $overrides['latitude'] ?? -16.5,
            'longitude' => $overrides['longitude'] ?? -68.15,
        ]);

        return $space;
    }
}
