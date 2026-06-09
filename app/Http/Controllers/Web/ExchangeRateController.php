<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExchangeRateController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->canManage($request), 403);

        $rates = ExchangeRate::query()
            ->where('company_id', $this->companyId())
            ->with('creator:id,name')
            ->latest('effective_date')
            ->latest('id')
            ->paginate(20);

        return view('exchange-rates.index', [
            'currentRate' => ExchangeRate::currentForCompany($this->companyId()),
            'rates' => $rates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'rate' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999'],
            'effective_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            ExchangeRate::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $this->companyId())
                ->where('from_currency', 'USD')
                ->where('to_currency', 'BOB')
                ->update(['is_active' => false]);

            ExchangeRate::query()->create([
                'company_id' => $this->companyId(),
                'from_currency' => 'USD',
                'to_currency' => 'BOB',
                'rate' => $data['rate'],
                'effective_date' => $data['effective_date'],
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
                'created_by' => $request->user()?->id,
            ]);
        });

        return redirect()
            ->route('exchange-rates.index')
            ->with('success', 'Tipo de cambio actualizado correctamente.');
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }

    private function canManage(Request $request): bool
    {
        return $request->user()?->can('exchange-rates.manage') === true
            || $request->user()?->can('occupancy.manage') === true;
    }
}
