<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Punishment\RequestLiftPlayerPunishmentRequest;
use App\Http\Requests\Punishment\ReviewLiftPlayerPunishmentRequest;
use App\Http\Requests\Punishment\StorePlayerPunishmentRequest;
use App\Models\Player;
use App\Models\PlayerPunishment;
use App\Models\RedCardArticle;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlayerPunishmentController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('punishments.view'), 403);

        $punishments = PlayerPunishment::query()
            ->with(['player', 'article', 'requestedBy', 'reviewedBy'])
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByRaw("CASE status WHEN 'lift_requested' THEN 0 WHEN 'active' THEN 1 ELSE 2 END")
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('punishments.index', compact('punishments'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()?->can('punishments.create'), 403);

        return view('punishments.create', [
            'teams' => Team::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'articles' => RedCardArticle::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->orderBy('number')
                ->get(),
        ]);
    }

    public function players(Team $team): JsonResponse
    {
        abort_unless(auth()->user()?->can('punishments.create'), 403);
        abort_unless(CompanyContext::belongsToUser($team->company_id, auth()->user()), 403);

        $players = TeamPlayer::query()
            ->with('player')
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->whereHas('player', fn ($query) => $query->where('is_active', true))
            ->get()
            ->sortBy(fn (TeamPlayer $teamPlayer): string => $teamPlayer->player?->full_name ?? '')
            ->map(fn (TeamPlayer $teamPlayer): array => [
                'value' => (string) $teamPlayer->player_id,
                'text' => trim(($teamPlayer->player?->full_name ?? '-').' · CI '.($teamPlayer->player?->ci ?? '-').' · '.($teamPlayer->player?->internal_code ?? '-')),
            ])
            ->values();

        return response()->json(['data' => $players]);
    }

    public function store(StorePlayerPunishmentRequest $request): RedirectResponse
    {
        $team = Team::query()
            ->whereKey($request->integer('team_id'))
            ->when(CompanyContext::id($request->user()), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->firstOrFail();

        $player = Player::query()
            ->whereKey($request->integer('player_id'))
            ->where('company_id', $team->company_id)
            ->firstOrFail();

        TeamPlayer::query()
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->where('player_id', $player->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->firstOrFail();

        RedCardArticle::query()
            ->whereKey($request->integer('red_card_article_id'))
            ->where('company_id', $player->company_id)
            ->firstOrFail();

        $durationType = $request->validated('duration_type');
        $durationValue = $durationType === 'indefinite' ? null : $request->integer('duration_value');
        abort_if($durationType !== 'indefinite' && $durationValue < 1, 422, 'La duracion es obligatoria.');

        $startsOn = Carbon::parse($request->validated('starts_on'));

        PlayerPunishment::query()->create([
            'company_id' => $player->company_id,
            'player_id' => $player->id,
            'red_card_article_id' => $request->integer('red_card_article_id'),
            'duration_type' => $durationType,
            'duration_value' => $durationValue,
            'starts_on' => $startsOn,
            'ends_on' => $this->endsOn($startsOn, $durationType, $durationValue),
            'reason' => $request->validated('reason'),
            'status' => PlayerPunishment::STATUS_ACTIVE,
        ]);

        return redirect()->route('punishments.index')->with('success', 'Castigo registrado correctamente.');
    }

    public function requestLift(RequestLiftPlayerPunishmentRequest $request, PlayerPunishment $punishment): RedirectResponse
    {
        $this->ensureVisible($punishment, 'punishments.request-lift');
        abort_unless($punishment->status === PlayerPunishment::STATUS_ACTIVE, 422, 'Solo se puede solicitar quitar un castigo activo.');

        $punishment->update([
            'status' => PlayerPunishment::STATUS_LIFT_REQUESTED,
            'lift_reason' => $request->validated('lift_reason'),
            'lift_requested_at' => now(),
            'lift_requested_by' => $request->user()?->id,
        ]);

        return redirect()->route('punishments.index')->with('success', 'Solicitud enviada para aprobacion.');
    }

    public function reviewLift(ReviewLiftPlayerPunishmentRequest $request, PlayerPunishment $punishment): RedirectResponse
    {
        $this->ensureVisible($punishment, 'punishments.approve-lift');
        abort_unless($punishment->status === PlayerPunishment::STATUS_LIFT_REQUESTED, 422, 'Este castigo no tiene solicitud pendiente.');

        $approved = $request->validated('decision') === 'approve';
        $punishment->update([
            'status' => $approved ? PlayerPunishment::STATUS_LIFTED : PlayerPunishment::STATUS_ACTIVE,
            'ends_on' => $approved ? today() : $punishment->ends_on,
            'lift_review_note' => $request->validated('lift_review_note'),
            'lift_reviewed_at' => now(),
            'lift_reviewed_by' => $request->user()?->id,
        ]);

        return redirect()->route('punishments.index')->with('success', $approved ? 'Castigo quitado correctamente.' : 'Solicitud rechazada.');
    }

    private function endsOn(Carbon $startsOn, string $durationType, ?int $durationValue): ?Carbon
    {
        return match ($durationType) {
            'months' => $startsOn->copy()->addMonthsNoOverflow((int) $durationValue),
            'years' => $startsOn->copy()->addYears((int) $durationValue),
            default => null,
        };
    }

    private function ensureVisible(PlayerPunishment $punishment, string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
        abort_unless(CompanyContext::belongsToUser($punishment->company_id, auth()->user()), 403);
    }
}
