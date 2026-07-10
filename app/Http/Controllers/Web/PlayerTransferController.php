<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerTransfer\ReviewPlayerTransferRequest;
use App\Http\Requests\PlayerTransfer\StorePlayerTransferRequest;
use App\Http\Requests\PlayerTransfer\UpdatePlayerTransferSettingRequest;
use App\Models\PlayerTransferRequest;
use App\Services\PlayerTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlayerTransferController extends Controller
{
    public function __construct(
        private readonly PlayerTransferService $transfers
    ) {}

    public function index(): View
    {
        return view('player-transfers.index', [
            'requests' => $this->transfers->requests(),
        ]);
    }

    public function settings(): RedirectResponse
    {
        return redirect()->route('league-settings.index');
    }

    public function updateSettings(UpdatePlayerTransferSettingRequest $request): RedirectResponse
    {
        try {
            $this->transfers->updateSetting($request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()->route('league-settings.index')->with('success', 'Configuracion de pases actualizada correctamente.');
    }

    public function create(Request $request): View
    {
        try {
            $context = $this->transfers->requestFormContext(
                $request->integer('tournament_id'),
                $request->integer('to_team_id'),
                $request->integer('player_id')
            );

            return view('player-transfers.partials.request-form', $context);
        } catch (ValidationException $exception) {
            return view('player-transfers.partials.request-form', [
                'error' => collect($exception->errors())->flatten()->first(),
            ]);
        }
    }

    public function store(StorePlayerTransferRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $transfer = $this->transfers->create($request->validated());
        } catch (ValidationException $exception) {
            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Solicitud de pase registrada correctamente.',
                'data' => ['id' => $transfer->id, 'code' => $transfer->code],
            ], 201);
        }

        return redirect()->route('player-transfers.index')->with('success', 'Solicitud de pase registrada correctamente.');
    }

    public function review(ReviewPlayerTransferRequest $request, PlayerTransferRequest $playerTransferRequest): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'approve') {
                $this->transfers->approve($playerTransferRequest, $request->user()->id, $request->validated('review_notes'));

                return redirect()->route('player-transfers.index')->with('success', 'Pase aprobado correctamente.');
            }

            $this->transfers->reject($playerTransferRequest, $request->user()->id, $request->validated('review_notes'));

            return redirect()->route('player-transfers.index')->with('success', 'Solicitud de pase rechazada.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }
    }
}
