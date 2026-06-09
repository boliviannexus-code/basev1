<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Services\CashRegisterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    public function __construct(
        private readonly CashRegisterService $cashRegisters,
    ) {}

    public function index(Request $request): View
    {
        $cashRegisters = CashRegister::query()
            ->where('company_id', $request->user()->company_id)
            ->with(['company', 'user', 'sales', 'expenses', 'lodgingPayments'])
            ->withCount('sales')
            ->latest('opened_at')
            ->paginate(15);

        $cashRegisters->getCollection()->each(function (CashRegister $register): void {
            $summary = $this->cashRegisters->cashSummary($register);
            $register->sales_total = $summary['sales_total'] + $summary['lodging_total'];
            $register->expenses_total = $summary['expenses'];
        });

        return view('sales.index', compact('cashRegisters'));
    }

    public function show(Request $request, CashRegister $cashRegister): View
    {
        abort_unless((int) $cashRegister->company_id === (int) $request->user()->company_id, 404);
        $cashRegister->load(['company', 'user']);

        return view('sales.show', [
            'cashRegister' => $cashRegister,
            'cashSummary' => $this->cashRegisters->cashSummary($cashRegister),
        ]);
    }

    public function void(Request $request, Sale $sale): RedirectResponse
    {
        abort_unless((int) $sale->cashRegister?->company_id === (int) $request->user()->company_id, 404);
        $sale->update(['status' => 'voided']);

        return redirect()->route('sales.cash-registers.show', $sale->cash_register_id)->with('success', 'Venta anulada.');
    }
}
