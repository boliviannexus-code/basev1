<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeagueSetting\UpdateLeagueSettingRequest;
use App\Models\Company;
use App\Models\LeagueSetting;
use App\Models\PlayerTransferSetting;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
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
}
