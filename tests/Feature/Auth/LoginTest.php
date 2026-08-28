<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_system_user_is_redirected_to_dashboard_instead_of_a_stale_unauthorized_url(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('dashboard.view');
        $user->givePermissionTo('dashboard.view');

        $response = $this
            ->withSession(['url.intended' => route('audits.index')])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('url.intended'));
    }

    public function test_public_user_keeps_the_intended_url_after_login(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->withSession(['url.intended' => route('public.reservations.index')])
            ->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('public.reservations.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_public_user_without_an_intended_url_is_redirected_to_reservations(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('public.reservations.index'));
        $this->assertAuthenticatedAs($user);
    }
}
