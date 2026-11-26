<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CashRegister;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SwitchCompanyController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
        ]);
        $destination = $data['company_id'] ?? null;
        DB::transaction(function () use ($request, $destination): void {
            $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if ((string) $user->company_id === (string) $destination) {
                return;
            }
            $outsideDestination = static fn ($query) => $query->where('user_id', $user->id)->where('status', 'open')
                ->when($destination !== null, fn ($query) => $query->where(fn ($query) => $query->whereNull('company_id')->orWhere('company_id', '!=', $destination)));
            if ($outsideDestination(CashRegister::query())->exists() || $outsideDestination(SpaceCashRegister::query())->exists()) {
                throw ValidationException::withMessages(['company_id' => 'Cierra tu caja abierta antes de cambiar de empresa.']);
            }
            $user->update(['company_id' => $destination]);
        });

        return redirect()->route('dashboard')->with('success', 'Empresa cambiada correctamente.');
    }
}
