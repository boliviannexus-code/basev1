<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CompanyPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_user_can_update_own_public_profile(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create([
            'country' => 'Bolivia',
            'city' => 'Cochabamba',
            'address' => 'Direccion antigua',
            'location_reference' => 'Referencia antigua',
            'is_online_enabled_by_admin' => false,
            'is_public_enabled' => false,
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);
        Permission::findOrCreate('company-public-profile.manage');
        $user->givePermissionTo('company-public-profile.manage');

        $this
            ->actingAs($user)
            ->put(route('company.public-profile.update'), [
                'public_name' => 'Nexus Travel',
                'public_slug' => '',
                'public_description' => 'Experiencias turisticas en Bolivia.',
                'phone' => '70000000',
                'whatsapp' => '70000000',
                'email' => 'contacto@nexus.test',
                'website' => 'https://nexus.test',
                'country' => 'Bolivia',
                'city' => 'La Paz',
                'address' => 'Av. Principal',
                'location_reference' => 'Frente a la plaza',
                'facebook_url' => 'https://facebook.com/nexus',
                'instagram_url' => 'https://instagram.com/nexus',
                'tiktok_url' => 'https://tiktok.com/@nexus',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
                'is_public_enabled' => '1',
                'is_online_enabled_by_admin' => '1',
            ])
            ->assertRedirect(route('company.public-profile.edit'))
            ->assertSessionHas('success', 'Perfil público actualizado correctamente.');

        $company->refresh();

        $this->assertSame('Nexus Travel', $company->public_name);
        $this->assertSame('nexus-travel', $company->public_slug);
        $this->assertTrue($company->is_public_enabled);
        $this->assertFalse($company->is_online_enabled_by_admin);
        $this->assertSame('Bolivia', $company->country);
        $this->assertSame('Cochabamba', $company->city);
        $this->assertSame('Direccion antigua', $company->address);
        $this->assertSame('Referencia antigua', $company->location_reference);
        $this->assertNotNull($company->logo);
        $this->assertNotNull($company->cover_image);
        Storage::disk('public')->assertExists($company->logo);
        Storage::disk('public')->assertExists($company->cover_image);
    }

    public function test_public_slug_must_be_unique(): void
    {
        Company::factory()->create([
            'public_slug' => 'nexus-travel',
        ]);

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);
        Permission::findOrCreate('company-public-profile.manage');
        $user->givePermissionTo('company-public-profile.manage');

        $this
            ->actingAs($user)
            ->from(route('company.public-profile.edit'))
            ->put(route('company.public-profile.update'), [
                'public_name' => 'Otra empresa',
                'public_slug' => 'nexus-travel',
                'is_public_enabled' => '1',
            ])
            ->assertRedirect(route('company.public-profile.edit'))
            ->assertSessionHasErrors('public_slug');
    }

    public function test_user_without_company_cannot_edit_public_profile(): void
    {
        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $this
            ->actingAs($user)
            ->get(route('company.public-profile.edit'))
            ->assertForbidden();
    }

    public function test_company_user_without_permission_cannot_edit_public_profile(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this
            ->actingAs($user)
            ->get(route('company.public-profile.edit'))
            ->assertForbidden();
    }
}
