<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeagueSetting\UpdateLeagueSettingRequest;
use App\Models\Company;
use App\Models\LeagueSetting;
use App\Models\PlayerTransferSetting;
use App\Services\MatchControlItemService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeagueSettingController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('league-settings.view'), 403);

        $companies = CompanyContext::scope(Company::query(), column: 'id')
            ->with(['leagueSetting', 'playerTransferSetting'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $companies->each(function (Company $company): void {
            $setting = $company->leagueSetting;

            if (! $setting) {
                $setting = $this->defaultSetting($company);
                $company->setRelation('leagueSetting', $setting);
            }

            if ((float) $setting->transfer_fee <= 0 && $company->playerTransferSetting?->fee_amount !== null) {
                $setting->transfer_fee = $company->playerTransferSetting->fee_amount;
            }
        });

        return view('league-settings.index', [
            'companies' => $companies,
            'fields' => $this->fields(),
        ]);
    }

    public function update(UpdateLeagueSettingRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $company = $this->companyFor($data['company_id'] ?? null);
        $costs = collect($this->fields())
            ->keys()
            ->mapWithKeys(fn (string $field): array => [$field => $data[$field]])
            ->all();

        $setting = LeagueSetting::query()->firstOrNew(['company_id' => $company->id]);
        $setting->fill($costs + [
            'company_id' => $company->id,
            'max_enabled_players_per_team_category' => $data['max_enabled_players_per_team_category'],
            'max_meeting_permissions_per_team' => $data['max_meeting_permissions_per_team'],
            'updated_by' => auth()->id(),
        ]);

        if (! $setting->exists) {
            $setting->created_by = auth()->id();
        }

        $setting->save();

        PlayerTransferSetting::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'fee_amount' => $setting->transfer_fee,
                'next_sequence' => $company->playerTransferSetting?->next_sequence ?? 1,
            ]
        );

        return redirect()
            ->route('league-settings.index')
            ->with('success', 'Configuracion de liga actualizada correctamente.');
    }

    public function appearance(): View
    {
        abort_unless(auth()->user()?->can('league-settings.view'), 403);

        return view('league-settings.appearance', [
            'companies' => CompanyContext::scope(Company::query(), column: 'id')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'colorFields' => $this->colorFields(),
        ]);
    }

    public function updateAppearance(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()?->can('league-settings.update') && CompanyContext::canOperate(auth()->user()), 403);

        $rules = [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];

        foreach (array_keys($this->colorFields()) as $field) {
            $rules[$field] = ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }

        $data = $request->validate($rules);
        $company = $this->companyFor($data['company_id'] ?? null);

        $company->update(collect(array_keys($this->colorFields()))
            ->mapWithKeys(fn (string $field): array => [$field => $data[$field] ?? null])
            ->all());

        return redirect()
            ->route('league-settings.appearance')
            ->with('success', 'Apariencia de la liga actualizada correctamente.');
    }

    public function matchControlItems(): View
    {
        abort_unless(auth()->user()?->can('league-settings.view'), 403);

        return view('league-settings.match-control-items', [
            'companies' => CompanyContext::scope(Company::query(), column: 'id')
                ->with(['matchControlItems' => fn ($query) => $query->orderBy('sort_order')->orderBy('label')])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'universalMatchControlItems' => MatchControlItemService::UNIVERSAL_ITEMS,
        ]);
    }

    public function updateMatchControlItems(Request $request, MatchControlItemService $controlItems): RedirectResponse
    {
        abort_unless(auth()->user()?->can('league-settings.update') && CompanyContext::canOperate(auth()->user()), 403);

        $data = $request->validate([
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'match_control_items' => ['nullable', 'array'],
            'match_control_items.*.id' => ['required', 'integer', 'exists:match_control_items,id'],
            'match_control_items.*.label' => ['required', 'string', 'max:120'],
            'match_control_items.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'match_control_items.*.is_active' => ['sometimes', 'boolean'],
            'new_match_control_item' => ['nullable', 'string', 'max:120'],
        ]);
        $company = $this->companyFor($data['company_id'] ?? null);

        $controlItems->syncItems(
            $company,
            $data['match_control_items'] ?? [],
            $data['new_match_control_item'] ?? null
        );

        return redirect()
            ->route('league-settings.match-control-items')
            ->with('success', 'Items de control actualizados correctamente.');
    }

    private function companyFor(?int $companyId): Company
    {
        $activeCompanyId = CompanyContext::id();

        if ($activeCompanyId !== null && $activeCompanyId > 0) {
            return Company::query()->findOrFail($activeCompanyId);
        }

        $company = Company::query()->whereKey($companyId)->firstOrFail();
        abort_unless(CompanyContext::belongsToUser($company->id, auth()->user()), 403);

        return $company;
    }

    private function defaultSetting(Company $company): LeagueSetting
    {
        $setting = new LeagueSetting(['company_id' => $company->id]);

        foreach (LeagueSetting::COST_FIELDS as $field) {
            $setting->{$field} = '0.00';
        }

        $setting->max_enabled_players_per_team_category = 0;
        $setting->max_meeting_permissions_per_team = 0;

        return $setting;
    }

    private function fields(): array
    {
        return [
            'transfer_fee' => ['label' => 'Costo de pases', 'icon' => 'ti-switch-horizontal'],
            'yellow_card_fee' => ['label' => 'Tarjeta amarilla', 'icon' => 'ti-cardboards'],
            'red_card_fee' => ['label' => 'Tarjeta roja', 'icon' => 'ti-cardboards'],
            'court_fee' => ['label' => 'Derecho de cancha', 'icon' => 'ti-map-pin'],
            'medical_fee' => ['label' => 'Costo medico', 'icon' => 'ti-stethoscope'],
            'scorer_fee' => ['label' => 'Costo planillero', 'icon' => 'ti-clipboard-text'],
            'ballboy_fee' => ['label' => 'Costo pasapelotas', 'icon' => 'ti-ball-football'],
            'referee_fee' => ['label' => 'Costo arbitros', 'icon' => 'ti-whistle'],
            'medicine_fee' => ['label' => 'Costo medicamentos', 'icon' => 'ti-pill'],
            'insurance_fee' => ['label' => 'Costo seguro', 'icon' => 'ti-shield-check'],
        ];
    }

    private function colorFields(): array
    {
        return [
            'interface_primary_color' => ['label' => 'Color principal', 'fallback' => '#206bc4'],
            'interface_secondary_color' => ['label' => 'Color secundario', 'fallback' => '#0f7b5f'],
            'interface_accent_color' => ['label' => 'Color de acento', 'fallback' => '#f5c542'],
            'interface_sidebar_color' => ['label' => 'Menu lateral', 'fallback' => '#1f2937'],
            'interface_login_background_color' => ['label' => 'Fondo del login', 'fallback' => '#f4f7f5'],
        ];
    }
}
