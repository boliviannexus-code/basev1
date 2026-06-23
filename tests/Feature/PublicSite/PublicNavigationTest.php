<?php

namespace Tests\Feature\PublicSite;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_user_sees_dashboard_link_in_public_navigation(): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('admin'));

        $this
            ->actingAs($user)
            ->view('layouts.public')
            ->assertSee('Volver al dashboard')
            ->assertSee(route('dashboard'), false)
            ->assertDontSee('Mis reservas');
    }

    public function test_external_customer_sees_reservations_link_in_public_navigation(): void
    {
        $user = User::factory()->create();

        $this
            ->actingAs($user)
            ->view('layouts.public')
            ->assertSee('Mis reservas')
            ->assertSee(route('public.reservations.index'), false)
            ->assertDontSee('Volver al dashboard');
    }
}
