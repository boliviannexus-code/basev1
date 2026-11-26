<?php

namespace Tests\Feature\Pos;

use App\Models\Company;
use App\Models\SpaceCashRegister;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CashCompanyChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_return_to_company_with_legacy_open_register(): void
    {
        $current = Company::factory()->create();
        $destination = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $current->id, 'is_active' => true]);
        SpaceCashRegister::factory()->create(['company_id' => $destination->id, 'user_id' => $user->id, 'status' => 'open']);
        app(UserService::class)->update($user, ['company_id' => $destination->id]);
        $this->assertSame($destination->id, $user->refresh()->company_id);
    }

    public function test_user_cannot_leave_company_with_open_register(): void
    {
        $current = Company::factory()->create();
        $destination = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $current->id, 'is_active' => true]);
        SpaceCashRegister::factory()->create(['company_id' => $current->id, 'user_id' => $user->id, 'status' => 'open']);
        $this->expectException(ValidationException::class);
        app(UserService::class)->update($user, ['company_id' => $destination->id]);
    }
}
