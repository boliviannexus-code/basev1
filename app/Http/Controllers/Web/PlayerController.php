<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Player\DeletePlayerPhotoRequest;
use App\Http\Requests\Player\StorePlayerRequest;
use App\Http\Requests\Player\UpdatePlayerPhotoRequest;
use App\Http\Requests\Player\UpdatePlayerRequest;
use App\Models\BiometricFingerprint;
use App\Models\Player;
use App\Models\TournamentTeamPlayer;
use App\Services\PlayerPhotoService;
use App\Services\PlayerQrCodeService;
use App\Services\PlayerService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class PlayerController extends Controller
{
    public function __construct(
        private readonly PlayerService $players,
        private readonly PlayerPhotoService $photos,
        private readonly PlayerQrCodeService $qrCodes
    ) {}

    public function index(Request $request): View
    {
        return view('players.index', [
            'players' => $this->players->paginate($request->string('q')->toString()),
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        if ($request->ajax()) {
            return view('players.partials.create-form', ['player' => null]);
        }

        return view('players.create', ['player' => null]);
    }

    public function store(StorePlayerRequest $request): JsonResponse|RedirectResponse
    {
        $player = $this->players->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jugador registrado correctamente.',
                'data' => ['id' => $player->id],
            ], 201);
        }

        return redirect()->route('players.index')->with('success', 'Jugador registrado correctamente.');
    }

    public function show(Request $request, Player $player): View
    {
        $this->authorizePlayerAccess($player);

        $player->load($this->playerHistoryRelations());
        $qrCodeDataUri = $this->qrCodeDataUri($player);
        $photoDataUri = $this->photos->dataUriFor($player);
        $rightIndexFingerprint = $this->rightIndexFingerprint($player);

        if ($request->ajax()) {
            return view('players.partials.show', compact('player', 'qrCodeDataUri', 'photoDataUri', 'rightIndexFingerprint'));
        }

        return view('players.show', compact('player', 'qrCodeDataUri', 'photoDataUri', 'rightIndexFingerprint'));
    }

    public function edit(Request $request, Player $player): View
    {
        $this->authorizePlayerAccess($player);

        if ($request->ajax()) {
            return view('players.partials.edit-form', compact('player'));
        }

        return view('players.edit', compact('player'));
    }

    public function editPhoto(Request $request, Player $player): View
    {
        $this->authorizePlayerAccess($player);

        $photoDataUri = $this->photos->dataUriFor($player);

        if ($request->ajax()) {
            return view('players.partials.photo-form', compact('player', 'photoDataUri'));
        }

        return view('players.photo', compact('player', 'photoDataUri'));
    }

    public function update(UpdatePlayerRequest $request, Player $player): JsonResponse|RedirectResponse
    {
        $this->authorizePlayerAccess($player);

        $player = $this->players->update($player, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jugador actualizado correctamente.',
                'data' => ['id' => $player->id],
            ]);
        }

        return redirect()->route('players.index')->with('success', 'Jugador actualizado correctamente.');
    }

    public function updatePhoto(UpdatePlayerPhotoRequest $request, Player $player): JsonResponse|RedirectResponse
    {
        $player = $this->photos->update($player, $request->file('photo'));

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Fotografia del jugador actualizada correctamente.',
                'data' => [
                    'id' => $player->id,
                    'photo_data_uri' => $this->photos->dataUriFor($player),
                    'photo_url' => $this->photos->url($player),
                    'photo_path' => $player->photo_path,
                ],
            ]);
        }

        return back()->with('success', 'Fotografia del jugador actualizada correctamente.');
    }

    public function destroyPhoto(DeletePlayerPhotoRequest $request, Player $player): JsonResponse|RedirectResponse
    {
        $this->photos->delete($player);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Fotografia del jugador eliminada correctamente.',
                'data' => ['id' => $player->id],
            ]);
        }

        return back()->with('success', 'Fotografia del jugador eliminada correctamente.');
    }

    public function destroy(Player $player): RedirectResponse
    {
        $this->authorizePlayerAccess($player);

        $this->players->delete($player);

        return redirect()->route('players.index')->with('success', 'Jugador eliminado correctamente.');
    }

    private function qrCodeDataUri(Player $player): ?string
    {
        if (blank($player->qr_code_path) && $player->tournamentTeamPlayers()
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->exists()) {
            $player = $this->qrCodes->ensureForPlayer($player);
        }

        return $this->qrCodes->dataUriFor($player);
    }

    private function authorizePlayerAccess(Player $player): void
    {
        if (CompanyContext::isGlobalAdmin()) {
            return;
        }

        abort_unless(
            CompanyContext::id() !== null
            && $player->teamPlayers()
                ->where('company_id', CompanyContext::id())
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->exists(),
            403
        );
    }

    private function playerHistoryRelations(): array
    {
        if (CompanyContext::isGlobalAdmin()) {
            return ['teamPlayers.team', 'teamPlayers.division'];
        }

        $companyId = CompanyContext::id();

        return [
            'teamPlayers' => fn ($query) => $query->where('company_id', $companyId),
            'teamPlayers.team',
            'teamPlayers.division',
        ];
    }

    private function rightIndexFingerprint(Player $player): ?BiometricFingerprint
    {
        if (! Schema::hasTable('biometric_fingerprints') || ! Schema::hasColumn('biometric_fingerprints', 'player_id')) {
            return null;
        }

        return BiometricFingerprint::query()
            ->where('player_id', $player->id)
            ->where('finger_position', 'right_index')
            ->where('is_active', true)
            ->latest('enrolled_at')
            ->latest()
            ->first();
    }
}
