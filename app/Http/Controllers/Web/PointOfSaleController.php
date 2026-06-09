<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\PointOfSale;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PointOfSaleController extends Controller
{
    public function index(): View
    {
        $pointOfSales = PointOfSale::query()->with(['branch', 'warehouse', 'users'])->orderByDesc('id')->paginate(15);

        return view('point-of-sales.index', compact('pointOfSales'));
    }

    public function create(Request $request): View
    {
        $data = $this->formData(null);

        if ($request->ajax()) {
            return view('point-of-sales.partials.create-form', $data);
        }

        return view('point-of-sales.create', $data);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request);
        $warehouse = $this->warehouseFrom($data, $request);
        $branchId = $this->branchIdFrom($data, $warehouse);

        if (($error = $this->warehouseError($data, $warehouse)) !== null) {
            throw ValidationException::withMessages(['warehouse_id' => $error]);
        }

        DB::transaction(function () use ($request, $data, $warehouse, $branchId): void {
            $sequence = ((int) PointOfSale::query()->where('company_id', $request->user()->company_id)->max('sequence_number')) + 1;
            $code = $this->codeFor($sequence);
            $pointOfSale = PointOfSale::query()->create([
                'company_id' => $request->user()->company_id,
                'branch_id' => $branchId,
                'warehouse_id' => $warehouse?->id,
                'name' => $data['name'],
                'code' => $code,
                'sequence_number' => $sequence,
                'receipt_prefix' => $data['receipt_prefix'] ?: $code,
                'receipt_next_number' => $data['receipt_next_number'] ?? 1,
                'receipt_digits' => $data['receipt_digits'] ?? 6,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            $this->syncUsers($pointOfSale, $data['users'] ?? []);
        });

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Punto de venta creado.',
                'refresh_url' => route('point-of-sales.index'),
            ], 201);
        }

        return redirect()->route('point-of-sales.index')->with('success', 'Punto de venta creado.');
    }

    public function show(Request $request, PointOfSale $pointOfSale): View
    {
        $pointOfSale->load(['branch', 'warehouse', 'users']);

        if ($request->ajax()) {
            return view('point-of-sales.partials.show', compact('pointOfSale'));
        }

        return view('point-of-sales.show', compact('pointOfSale'));
    }

    public function edit(Request $request, PointOfSale $pointOfSale): View
    {
        $pointOfSale->load('users');

        $data = $this->formData($pointOfSale);

        if ($request->ajax()) {
            return view('point-of-sales.partials.edit-form', $data);
        }

        return view('point-of-sales.edit', $data);
    }

    public function update(Request $request, PointOfSale $pointOfSale): JsonResponse|RedirectResponse
    {
        $data = $this->validated($request, $pointOfSale);
        $warehouse = $this->warehouseFrom($data, $request);
        $branchId = $this->branchIdFrom($data, $warehouse);

        if (($error = $this->warehouseError($data, $warehouse, $pointOfSale)) !== null) {
            throw ValidationException::withMessages(['warehouse_id' => $error]);
        }

        $lastSaleSequence = (int) $pointOfSale->cashRegisters()
            ->join('sales', 'sales.cash_register_id', '=', 'cash_registers.id')
            ->max('sales.sequence_number');

        if ((int) $data['receipt_next_number'] <= $lastSaleSequence) {
            return back()->withErrors(['receipt_next_number' => 'El siguiente numero debe ser mayor al ultimo comprobante emitido.'])->withInput();
        }

        DB::transaction(function () use ($pointOfSale, $data, $warehouse): void {
            $pointOfSale->update([
                'branch_id' => $this->branchIdFrom($data, $warehouse),
                'warehouse_id' => $warehouse?->id,
                'name' => $data['name'],
                'receipt_prefix' => $data['receipt_prefix'] ?: $pointOfSale->code,
                'receipt_next_number' => $data['receipt_next_number'] ?? 1,
                'receipt_digits' => $data['receipt_digits'] ?? 6,
                'is_active' => (bool) ($data['is_active'] ?? false),
            ]);

            $this->syncUsers($pointOfSale, $data['users'] ?? []);
        });

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Punto de venta actualizado.',
                'refresh_url' => route('point-of-sales.index'),
            ]);
        }

        return redirect()->route('point-of-sales.index')->with('success', 'Punto de venta actualizado.');
    }

    public function destroy(PointOfSale $pointOfSale): RedirectResponse
    {
        $pointOfSale->delete();

        return redirect()->route('point-of-sales.index')->with('success', 'Punto de venta eliminado.');
    }

    private function validated(Request $request, ?PointOfSale $pointOfSale = null): array
    {
        return $request->validate([
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $request->user()->company_id)],
            'warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('company_id', $request->user()->company_id)],
            'name' => ['required', 'string', 'max:255'],
            'receipt_prefix' => ['nullable', 'string', 'max:40'],
            'receipt_next_number' => ['nullable', 'integer', 'min:1'],
            'receipt_digits' => ['nullable', 'integer', 'min:1', 'max:12'],
            'users' => ['array'],
            'users.*' => ['integer', Rule::exists('users', 'id')->where('company_id', $request->user()->company_id)],
            'is_active' => ['boolean'],
        ]);
    }

    private function formData(?PointOfSale $pointOfSale): array
    {
        $companyId = auth()->user()?->company_id;

        return [
            'pointOfSale' => $pointOfSale,
            'branches' => Branch::query()->where('company_id', $companyId)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $companyId)->with('branch')->orderBy('name')->get()->groupBy('branch_id'),
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get(),
        ];
    }

    private function syncUsers(PointOfSale $pointOfSale, array $userIds): void
    {
        DB::table('point_of_sale_user')
            ->whereIn('user_id', $userIds)
            ->whereNot('point_of_sale_id', $pointOfSale->id)
            ->delete();

        $pointOfSale->users()->sync($userIds);
    }

    private function warehouseFrom(array $data, Request $request): ?Warehouse
    {
        if (empty($data['warehouse_id'])) {
            return null;
        }

        return Warehouse::query()
            ->whereKey($data['warehouse_id'])
            ->where('company_id', $request->user()->company_id)
            ->first();
    }

    private function branchIdFrom(array $data, ?Warehouse $warehouse): ?int
    {
        return ! empty($data['branch_id']) ? (int) $data['branch_id'] : $warehouse?->branch_id;
    }

    private function warehouseError(array $data, ?Warehouse $warehouse, ?PointOfSale $pointOfSale = null): ?string
    {
        if (empty($data['warehouse_id'])) {
            return null;
        }

        if (! $warehouse) {
            return 'El almacen debe pertenecer a tu empresa.';
        }

        if (! empty($data['branch_id']) && (int) $warehouse->branch_id !== (int) $data['branch_id']) {
            return 'El almacen debe pertenecer a la sucursal seleccionada.';
        }

        $query = PointOfSale::query()->where('warehouse_id', $warehouse->id);

        if ($pointOfSale) {
            $query->whereKeyNot($pointOfSale->id);
        }

        if ($query->exists()) {
            return 'El almacen ya esta vinculado a otro punto de venta.';
        }

        return null;
    }

    private function codeFor(int $sequence): string
    {
        return 'PV-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
