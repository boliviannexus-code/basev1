<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExtraChargeCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExtraChargeCategoryController extends Controller
{
    public function index(): View
    {
        ExtraChargeCategory::ensureDefaultsForCompany($this->companyId());

        $categories = ExtraChargeCategory::query()
            ->where('company_id', $this->companyId())
            ->withCount(['accountStatementItems', 'reservationExtraCharges'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('extra-charge-categories.index', [
            'categories' => $categories,
            'category' => new ExtraChargeCategory([
                'currency' => 'BOB',
                'is_active' => true,
                'sort_order' => 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ExtraChargeCategory::query()->create([
            'company_id' => $this->companyId(),
            ...$this->validated($request),
        ]);

        return redirect()
            ->route('extra-charge-categories.index')
            ->with('success', 'Categoria de cargo extra creada correctamente.');
    }

    public function update(Request $request, ExtraChargeCategory $extraChargeCategory): RedirectResponse
    {
        $this->ensureOwnership($extraChargeCategory);
        $extraChargeCategory->update($this->validated($request, $extraChargeCategory));

        return redirect()
            ->route('extra-charge-categories.index')
            ->with('success', 'Categoria de cargo extra actualizada correctamente.');
    }

    public function toggle(ExtraChargeCategory $extraChargeCategory): RedirectResponse
    {
        $this->ensureOwnership($extraChargeCategory);
        $extraChargeCategory->update(['is_active' => ! $extraChargeCategory->is_active]);

        return back()->with('success', 'Estado de categoria actualizado.');
    }

    public function destroy(ExtraChargeCategory $extraChargeCategory): RedirectResponse
    {
        $this->ensureOwnership($extraChargeCategory);

        if ($extraChargeCategory->is_protected) {
            return back()->withErrors(['category' => 'Esta categoria base no se puede eliminar. Puedes desactivarla.']);
        }

        if ($extraChargeCategory->accountStatementItems()->exists() || $extraChargeCategory->reservationExtraCharges()->exists()) {
            return back()->withErrors(['category' => 'No se puede eliminar una categoria con cargos registrados.']);
        }

        $extraChargeCategory->delete();

        return back()->with('success', 'Categoria eliminada correctamente.');
    }

    private function validated(Request $request, ?ExtraChargeCategory $category = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique((new ExtraChargeCategory)->getTable(), 'name')
                    ->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $this->companyId()))
                    ->whereNull('deleted_at')
                    ->ignore($category?->id),
            ],
            'default_unit_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]) + ['currency' => 'BOB'];
    }

    private function ensureOwnership(ExtraChargeCategory $category): void
    {
        abort_unless((int) $category->company_id === $this->companyId(), 404);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
