<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BiometricFingerprint;
use App\Models\Player;
use App\Services\Biometrics\BiometricEngineClient;
use App\Support\CompanyContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class PlayerBiometricRegistrationController extends Controller
{
    private const FINGER_POSITION = 'right_index';

    public function create(Request $request, Player $player): View
    {
        $activeFingerprint = $this->activeRightIndexFingerprint($player);

        $view = $request->ajax()
            ? 'players.partials.biometric-registration-form'
            : 'players.biometric-registration';

        return view($view, [
            'player' => $player,
            'activeFingerprint' => $activeFingerprint,
        ]);
    }

    public function store(Request $request, Player $player, BiometricEngineClient $engine): JsonResponse
    {
        if (! Schema::hasTable('biometric_fingerprints')) {
            return response()->json([
                'success' => false,
                'message' => 'Ejecuta php artisan migrate antes de guardar huellas biometricas.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'sample_image' => ['required', 'string', 'min:100', 'max:2097152'],
            'quality_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Revisa los datos de la huella capturada.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($this->activeRightIndexFingerprint($player)) {
            return response()->json([
                'success' => false,
                'message' => 'El jugador ya tiene registrado el indice derecho.',
                'errors' => [
                    'sample_image' => ['El jugador ya tiene registrado el indice derecho.'],
                ],
            ], 422);
        }

        $data = $validator->validated();

        try {
            $template = $engine->createTemplate($data['sample_image']);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con el microservicio biometrico.',
            ], 503);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'El motor biometrico no pudo generar el template de la huella.',
            ], 502);
        }

        $user = $request->user();
        $fingerprint = BiometricFingerprint::query()->create([
            'company_id' => CompanyContext::activeCompany($user)?->id ?? $user?->company_id,
            'user_id' => $user?->id,
            'player_id' => $player->id,
            'finger_position' => self::FINGER_POSITION,
            'sample_image' => encrypt($data['sample_image']),
            'template_data' => encrypt($template['template']),
            'template_format' => $template['format'] ?? 'sourceafis_3.18.1_base64',
            'format' => 'png_base64',
            'quality_score' => $data['quality_score'] ?? null,
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Huella del jugador guardada correctamente.',
            'data' => [
                'id' => $fingerprint->id,
                'player_id' => $player->id,
                'finger_position' => $fingerprint->finger_position,
                'enrolled_at' => $fingerprint->enrolled_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    private function activeRightIndexFingerprint(Player $player): ?BiometricFingerprint
    {
        if (! Schema::hasTable('biometric_fingerprints') || ! Schema::hasColumn('biometric_fingerprints', 'player_id')) {
            return null;
        }

        return BiometricFingerprint::query()
            ->where('player_id', $player->id)
            ->where('finger_position', self::FINGER_POSITION)
            ->where('is_active', true)
            ->latest('enrolled_at')
            ->latest()
            ->first();
    }
}
