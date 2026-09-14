<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Matchday;
use App\Models\ExtraCharge;
use App\Models\ExtraChargeInstallment;
use App\Models\TeamAccountCharge;
use App\Models\MatchdayCourtFeeStatement;
use App\Services\MatchdayCourtFeeStatementService;
use App\Services\MatchdayCourtFeePdfService;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Requests\ExtraCharge\GenerateMatchdayExtraChargesRequest;
use App\Support\CompanyContext;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchdayCourtFeeController extends Controller
{
    public function index(): View
    {
        $matchdays = CompanyContext::scope(Matchday::query())
            ->with(['season:id,name,year', 'courtFeeStatement:id,matchday_id,status,revision'])
            ->withCount(['dates', 'fixtureMatches'])
            ->orderByDesc('scheduled_date')
            ->orderByDesc('id')
            ->paginate(20);

        return view('matchday-court-fees.index', compact('matchdays'));
    }

    public function show(Matchday $matchday): View
    {
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);

        return view('matchday-court-fees.show', $this->statementContext($matchday));
    }

    public function pdf(Matchday $matchday, MatchdayCourtFeePdfService $pdf): Response
    {
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        $statement = app(MatchdayCourtFeeStatementService::class)->forMatchday($matchday);
        abort_unless($statement->status === MatchdayCourtFeeStatement::CONSOLIDATED, 422, 'Solo se puede imprimir un derecho consolidado.');

        return $pdf->render($this->statementContext($matchday));
    }

    private function statementContext(Matchday $matchday): array
    {
        $matchday->load([
            'company:id,name',
            'season:id,name,year',
            'fixtureMatches.tournament.courtFees.items',
            'fixtureMatches.homeTeam:id,name',
            'fixtureMatches.awayTeam:id,name',
        ]);

        $teamCharges = $this->teamCharges($matchday);
        $statement = app(MatchdayCourtFeeStatementService::class)->forMatchday($matchday);
        $extraInstallments = ExtraChargeInstallment::query()->where('matchday_id', $matchday->id)->where('status', 'active')
            ->with(['extraCharge:id,name,installments', 'team:id,name'])->get()->groupBy(fn ($item) => $item->tournament_id.'-'.$item->team_id);
        $extraChargeColumns = $extraInstallments->flatten(1)
            ->pluck('extraCharge')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
        $teamIds = $teamCharges->pluck('team.id')->unique()->values();
        $accountCharges = TeamAccountCharge::query()
            ->whereIn('team_id', $teamIds)
            ->where(function ($query) use ($matchday): void {
                $query->where('status', 'pending')
                    ->orWhere(fn ($query) => $query->where('status', 'applied')->where('applied_matchday_id', $matchday->id));
            })
            ->where('source_matchday_id', '!=', $matchday->id)
            ->with('sourceMatchday:id,name,number')
            ->get()
            ->groupBy(fn ($item) => $item->tournament_id.'-'.$item->team_id);
        $accountChargeColumns = $accountCharges->flatten(1)->unique('concept_key')->sortBy('description')->values();

        return compact('matchday', 'statement', 'teamCharges', 'extraInstallments', 'extraChargeColumns', 'accountCharges', 'accountChargeColumns');
    }

    public function generateForm(Request $request, Matchday $matchday): View
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_if(app(MatchdayCourtFeeStatementService::class)->isLocked($matchday), 422, 'El derecho de cancha está consolidado. Usa Editar para reabrirlo.');

        $charges = ExtraCharge::query()
            ->where('company_id', $matchday->company_id)
            ->where('is_active', true)
            ->whereHas('assignments')
            ->withCount('assignments')
            ->orderBy('name')
            ->get();

        return view('matchday-court-fees.partials.generate-form', compact('matchday', 'charges'));
    }

    public function generate(GenerateMatchdayExtraChargesRequest $request, Matchday $matchday): JsonResponse|RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_if(app(MatchdayCourtFeeStatementService::class)->isLocked($matchday), 422, 'El derecho de cancha está consolidado.');
        $matchday->load(['fixtureMatches.homeTeam:id,name', 'fixtureMatches.awayTeam:id,name', 'fixtureMatches.tournament']);
        $rows = $this->teamCharges($matchday);
        $charges = ExtraCharge::query()
            ->where('company_id', $matchday->company_id)
            ->where('is_active', true)
            ->whereKey($request->validated('extra_charge_ids', []))
            ->with('assignments')
            ->get();

        DB::transaction(function () use ($rows, $charges, $matchday): void {
            foreach ($rows as $row) {
                foreach ($charges as $charge) {
                    $applies = $charge->assignments->contains(fn ($assignment): bool => (int)$assignment->tournament_id === (int)$row['tournament']->id && ($assignment->all_teams || (int)$assignment->team_id === (int)$row['team']->id));
                    if (! $applies) continue;
                    $existing = ExtraChargeInstallment::query()->where(['extra_charge_id' => $charge->id, 'matchday_id' => $matchday->id, 'tournament_id' => $row['tournament']->id, 'team_id' => $row['team']->id])->first();
                    if ($existing?->status === 'active') continue;
                    $activeNumbers = ExtraChargeInstallment::query()->where(['extra_charge_id' => $charge->id, 'tournament_id' => $row['tournament']->id, 'team_id' => $row['team']->id])->where('status', 'active')->pluck('installment_number');
                    $paid = $activeNumbers->count();
                    if ($paid >= $charge->installments) continue;
                    $number = collect(range(1, $charge->installments))->first(fn (int $candidate): bool => ! $activeNumbers->contains($candidate));
                    $regular = round((float)$charge->amount_per_team / $charge->installments, 2);
                    $previous = ExtraChargeInstallment::query()->where(['extra_charge_id' => $charge->id, 'tournament_id' => $row['tournament']->id, 'team_id' => $row['team']->id])->where('status', 'active')->sum('amount');
                    $amount = $number === $charge->installments ? round((float)$charge->amount_per_team - (float)$previous, 2) : $regular;
                    $data = ['company_id' => $matchday->company_id, 'extra_charge_id' => $charge->id, 'matchday_id' => $matchday->id, 'tournament_id' => $row['tournament']->id, 'team_id' => $row['team']->id, 'installment_number' => $number, 'amount' => $amount, 'status' => 'active', 'voided_at' => null, 'voided_by' => null];
                    $existing ? $existing->update($data) : ExtraChargeInstallment::query()->create($data);
                }
            }

            foreach ($rows as $row) {
                TeamAccountCharge::query()
                    ->where('team_id', $row['team']->id)
                    ->where('tournament_id', $row['tournament']->id)
                    ->where('status', 'pending')
                    ->where('source_matchday_id', '!=', $matchday->id)
                    ->get()
                    ->each(fn (TeamAccountCharge $charge) => $charge->update([
                        'status' => 'applied',
                        'applied_matchday_id' => $matchday->id,
                        'applied_at' => now(),
                    ]));
            }
        });

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Cargos y cuotas seleccionados aplicados correctamente.']);
        }

        return redirect()->route('matchday-court-fees.show', $matchday)->with('success', 'Cargos y cuotas seleccionados aplicados correctamente.');
    }

    public function destroyInstallment(ExtraChargeInstallment $installment): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($installment->company_id, auth()->user()), 403);
        $installment->loadMissing('extraCharge');
        $matchday = Matchday::query()->findOrFail($installment->matchday_id);
        abort_if(app(MatchdayCourtFeeStatementService::class)->isLocked($matchday), 422, 'El derecho de cancha está consolidado.');
        $installment->update(['status' => 'voided', 'voided_at' => now(), 'voided_by' => auth()->id()]);

        return redirect()->route('matchday-court-fees.show', $installment->matchday_id)
            ->with('success', 'La cuota fue quitada y quedó registrada como anulada.');
    }

    public function destroyChargeInstallments(Matchday $matchday, ExtraCharge $extraCharge): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_unless((int) $extraCharge->company_id === (int) $matchday->company_id, 403);
        abort_if(app(MatchdayCourtFeeStatementService::class)->isLocked($matchday), 422, 'El derecho de cancha está consolidado.');

        $installments = ExtraChargeInstallment::query()
            ->where('matchday_id', $matchday->id)
            ->where('extra_charge_id', $extraCharge->id)
            ->where('status', 'active')
            ->get();

        foreach ($installments as $installment) {
            $installment->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => auth()->id(),
            ]);
        }

        return redirect()->route('matchday-court-fees.show', $matchday)
            ->with('success', $installments->count().' cuota(s) de '.$extraCharge->name.' fueron quitadas y conservadas en el historial.');
    }

    public function consolidate(Matchday $matchday, MatchdayCourtFeeStatementService $statements): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        $statement = $statements->forMatchday($matchday);
        $statement->update(['status'=>MatchdayCourtFeeStatement::CONSOLIDATED,'revision'=>$statement->revision + 1,'consolidated_at'=>now(),'consolidated_by'=>auth()->id(),'source_changed_at'=>null]);
        return back()->with('success', 'Derecho de cancha consolidado correctamente.');
    }

    public function reopen(Matchday $matchday, MatchdayCourtFeeStatementService $statements): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.update'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        $statements->forMatchday($matchday)->update(['status'=>MatchdayCourtFeeStatement::PENDING]);
        return back()->with('success', 'Derecho de cancha habilitado para edición.');
    }

    private function teamCharges(Matchday $matchday): Collection
    {
        return $matchday->fixtureMatches
            ->flatMap(function ($match): array {
                return collect([$match->homeTeam, $match->awayTeam])
                    ->filter()
                    ->map(fn ($team): array => [
                        'key' => $match->tournament_id.'-'.$team->id,
                        'team' => $team,
                        'tournament' => $match->tournament,
                    ])->all();
            })
            ->unique('key')
            ->sortBy(fn (array $row): string => ($row['tournament']?->name ?? '').'|'.$row['team']->name)
            ->values()
            ->map(function (array $row): array {
                $fees = $row['tournament']?->courtFees?->where('is_active', true)->values() ?? collect();
                $row['fees'] = $fees;
                $row['total'] = $fees->sum(fn ($fee): float => (float) $fee->items->sum('cost'));

                return $row;
            });
    }
}
