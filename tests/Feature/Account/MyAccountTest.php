<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class MyAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_my_account(): void
    {
        $this->get(route('my-account.edit'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_my_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('my-account.edit'))
            ->assertOk()
            ->assertSee('Cambiar contraseña')
            ->assertSee('Código de caja');
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->patch(route('my-account.password.update'), [
                'current_password' => 'old-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('my-account.edit'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password', (string) $user->refresh()->password));
    }

    public function test_current_password_is_required_to_change_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)
            ->patch(route('my-account.password.update'), [
                'current_password' => 'incorrect-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertSessionHasErrors('current_password', null, 'passwordUpdate');

        $this->assertTrue(Hash::check('old-password', (string) $user->refresh()->password));
    }

    public function test_user_can_change_own_transaction_pin(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password')]);

        $this->actingAs($user)
            ->patch(route('my-account.transaction-pin.update'), [
                'current_password' => 'password',
                'transaction_pin' => '4826',
                'transaction_pin_confirmation' => '4826',
            ])
            ->assertRedirect(route('my-account.edit'))
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('4826', (string) $user->refresh()->transaction_pin));
    }

    public function test_transaction_pin_must_be_unique_within_company(): void
    {
        $company = Company::factory()->create();
        User::factory()->create([
            'company_id' => $company->id,
            'transaction_pin' => Hash::make('4826'),
        ]);
        $user = User::factory()->create([
            'company_id' => $company->id,
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user)
            ->patch(route('my-account.transaction-pin.update'), [
                'current_password' => 'password',
                'transaction_pin' => '4826',
                'transaction_pin_confirmation' => '4826',
            ])
            ->assertSessionHasErrors('transaction_pin', null, 'transactionPinUpdate');

        $this->assertNull($user->refresh()->transaction_pin);
    }
}
