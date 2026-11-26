<?php

namespace Tests\Feature\Pos;

use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashRegister;
use App\Models\User;
use App\Services\CashRegisterService;
use App\Services\SpaceCashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedCashRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_screens_share_one_opening_summary_and_close(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $pos = app(CashRegisterService::class);
        $spaces = app(SpaceCashRegisterService::class);
        $register = $pos->openForUser($user, 100);
        $space = $spaces->currentForUser($user);
        $method = PaymentMethod::query()->create(['company_id' => $company->id, 'name' => 'Efectivo', 'is_active' => true]);
        SpaceCashIncome::query()->create([
            'company_id' => $company->id, 'space_cash_register_id' => $space->id, 'user_id' => $user->id,
            'payment_method_id' => $method->id, 'receipt_number' => 'TEST-1', 'detail' => 'Ingreso', 'amount' => 25, 'received_at' => now(),
        ]);
        $this->assertSame(125.0, $pos->cashSummary($register)['available']);
        $this->assertSame(125.0, $spaces->cashSummary($space)['available']);
        $this->assertSame(100.0, $spaces->cashSummary($space)['opening']);
        $spaces->closeForUser($user, 125);
        $this->assertSame('closed', $register->refresh()->status);
        $this->assertSame('closed', $space->refresh()->status);
        $this->assertSame('125.00', $register->closing_amount);
        $this->assertSame(125.0, $pos->cashSummary($register)['available']);
    }

    public function test_opening_from_spaces_creates_a_pos_session_for_the_same_company_and_user(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $other = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $space = app(SpaceCashRegisterService::class)->openForUser($user, 50);
        $register = app(CashRegisterService::class)->currentForUser($user);
        $this->assertSame($register->id, $space->cash_register_id);
        $this->assertNull(app(CashRegisterService::class)->currentForUser($other));
        app(CashRegisterService::class)->closeForUser($user, 50);
        $this->assertSame('closed', SpaceCashRegister::query()->findOrFail($space->id)->status);
    }
}
