<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this
            ->post(route('login.store'), [
                'email' => 'inactive@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_from_inactive_company_cannot_login(): void
    {
        $company = Company::factory()->create(['is_active' => false]);
        User::factory()->for($company)->create([
            'email' => 'league@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this
            ->post(route('login.store'), [
                'email' => 'league@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        User::factory()->create([
            'email' => 'limited@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'limited@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this
            ->post(route('login.store'), [
                'email' => 'limited@example.com',
                'password' => 'password123',
            ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deactivating_user_revokes_sessions_and_tokens(): void
    {
        $actor = $this->adminUser(['users.edit']);
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('api-token');
        DB::table('sessions')->insert([
            'id' => 'session-to-close',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this
            ->actingAs($actor)
            ->patch(route('users.toggle-status', $user))
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'session-to-close']);
    }

    public function test_changing_password_revokes_sessions_and_tokens(): void
    {
        $actor = $this->adminUser(['users.change-password']);
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->createToken('api-token');
        DB::table('sessions')->insert([
            'id' => 'password-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this
            ->actingAs($actor)
            ->patch(route('users.change-password', $user), [
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'password-session']);
        $this->assertTrue(Hash::check('new-password123', $user->refresh()->password));
    }

    public function test_deactivating_company_revokes_its_users_sessions_and_tokens(): void
    {
        $actor = $this->adminUser(['companies.update']);
        $company = Company::factory()->create(['is_active' => true]);
        $user = User::factory()->for($company)->create(['is_active' => true]);
        $token = $user->createToken('api-token');
        DB::table('sessions')->insert([
            'id' => 'company-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $this
            ->actingAs($actor)
            ->put(route('companies.update', $company), [
                'name' => $company->name,
                'code' => $company->code,
                'phone' => $company->phone,
                'email' => $company->email,
                'address' => $company->address,
                'city' => $company->city,
                'country' => $company->country,
                'report_footer' => $company->report_footer,
                'is_active' => '0',
            ])
            ->assertRedirect(route('companies.index'));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'company-session']);
    }

    public function test_api_rejects_inactive_user_and_does_not_create_token(): void
    {
        User::factory()->create([
            'email' => 'api-inactive@example.com',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this
            ->postJson(route('api.v1.login'), [
                'email' => 'api-inactive@example.com',
                'password' => 'password123',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_api_request_is_rejected_when_account_becomes_inactive(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        Sanctum::actingAs($user);

        $this
            ->postJson(route('api.v1.logout'))
            ->assertForbidden();
    }

    private function adminUser(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $role = Role::findOrCreate('super_admin');
        $role->givePermissionTo($permissions);

        $user = User::factory()->create(['company_id' => null, 'is_active' => true]);
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }
}
