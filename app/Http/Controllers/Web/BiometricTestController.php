<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BiometricFingerprint;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class BiometricTestController extends Controller
{
    public function index(Request $request): View
    {
        $fingerprints = collect();

        if (Schema::hasTable('biometric_fingerprints')) {
            $fingerprints = BiometricFingerprint::query()
                ->where('user_id', $request->user()->id)
                ->latest('enrolled_at')
                ->latest()
                ->get();
        }

        return view('biometric.test', [
            'fingerprints' => $fingerprints,
        ]);
    }

    public function enroll(Request $request): JsonResponse
    {
        if (! Schema::hasTable('biometric_fingerprints')) {
            return response()->json([
                'success' => false,
                'message' => 'Ejecuta php artisan migrate antes de guardar huellas biometricas.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'sample_image' => ['required', 'string', 'min:100', 'max:2097152'],
            'finger_position' => ['nullable', 'string', 'max:50'],
            'quality_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Revisa los datos de la huella capturada.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $data = $validator->validated();

        $fingerprint = BiometricFingerprint::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'finger_position' => $data['finger_position'] ?? null,
            'sample_image' => encrypt($data['sample_image']),
            'format' => 'png_base64',
            'quality_score' => $data['quality_score'] ?? null,
            'is_active' => true,
            'enrolled_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Huella guardada correctamente.',
            'data' => [
                'id' => $fingerprint->id,
                'format' => $fingerprint->format,
                'quality_score' => $fingerprint->quality_score,
                'enrolled_at' => $fingerprint->enrolled_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        if (! Schema::hasTable('biometric_fingerprints')) {
            return response()->json([
                'success' => false,
                'message' => 'Ejecuta php artisan migrate antes de verificar huellas biometricas.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'sample_image' => ['required', 'string', 'min:100', 'max:2097152'],
            'threshold' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Revisa la huella candidata capturada.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $fingerprint = BiometricFingerprint::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->latest('enrolled_at')
            ->latest()
            ->first();

        if (! $fingerprint) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes una huella activa guardada para comparar.',
            ], 404);
        }

        $engineUrl = rtrim((string) config('services.biometric_engine.url'), '/');

        if ($engineUrl === '') {
            return response()->json([
                'success' => false,
                'message' => 'Configura BIOMETRIC_ENGINE_URL antes de verificar huellas.',
            ], 503);
        }

        $data = $validator->validated();
        $threshold = (float) ($data['threshold'] ?? config('services.biometric_engine.threshold', 40));

        try {
            $storedImage = decrypt($fingerprint->sample_image);
        } catch (DecryptException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer la huella guardada.',
            ], 500);
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->post($engineUrl.'/compare', [
                    'stored_image' => $storedImage,
                    'candidate_image' => $data['sample_image'],
                    'threshold' => $threshold,
                ]);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con el microservicio biometrico.',
            ], 503);
        } finally {
            unset($storedImage);
        }

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'El motor biometrico no pudo comparar la huella.',
                'data' => [
                    'status' => $response->status(),
                ],
            ], 502);
        }

        $payload = $response->json();

        return response()->json([
            'success' => true,
            'message' => ($payload['match'] ?? false) ? 'Huella verificada correctamente.' : 'La huella no coincide.',
            'data' => [
                'match' => (bool) ($payload['match'] ?? false),
                'score' => isset($payload['score']) ? (float) $payload['score'] : null,
                'threshold' => isset($payload['threshold']) ? (float) $payload['threshold'] : $threshold,
            ],
        ]);
    }
}
