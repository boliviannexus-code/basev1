<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BiometricFingerprint;
use App\Services\Biometrics\BiometricEngineClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use RuntimeException;

class BiometricTestController extends Controller
{
    public function index(Request $request): View
    {
        $fingerprints = collect();

        if (Schema::hasTable('biometric_fingerprints')) {
            $fingerprints = BiometricFingerprint::query()
                ->where('user_id', $request->user()->id)
                ->where('is_active', true)
                ->latest('enrolled_at')
                ->latest()
                ->get();
        }

        return view('biometric.test', [
            'fingerprints' => $fingerprints,
        ]);
    }

    public function enroll(Request $request, BiometricEngineClient $engine): JsonResponse
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
        $fingerPosition = $data['finger_position'] ?? null;

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

        if ($fingerPosition) {
            BiometricFingerprint::query()
                ->where('user_id', $user->id)
                ->where('finger_position', $fingerPosition)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $fingerprint = BiometricFingerprint::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'finger_position' => $fingerPosition,
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
            'message' => 'Huella guardada correctamente.',
            'data' => [
                'id' => $fingerprint->id,
                'format' => $fingerprint->format,
                'template_format' => $fingerprint->template_format,
                'quality_score' => $fingerprint->quality_score,
                'enrolled_at' => $fingerprint->enrolled_at?->format('Y-m-d H:i:s'),
            ],
        ], 201);
    }

    public function verify(Request $request, BiometricEngineClient $engine): JsonResponse
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

        $data = $validator->validated();
        $threshold = (float) ($data['threshold'] ?? config('services.biometric_engine.threshold', 40));

        try {
            $storedTemplate = decrypt($fingerprint->template_data);
        } catch (DecryptException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer el template de la huella guardada.',
            ], 500);
        }

        try {
            $candidateTemplate = $engine->createTemplate($data['sample_image']);
            $payload = $engine->compareTemplates($storedTemplate, $candidateTemplate['template'], $threshold);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con el microservicio biometrico.',
            ], 503);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'El motor biometrico no pudo comparar la huella.',
            ], 502);
        } finally {
            unset($storedTemplate, $candidateTemplate);
        }

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

    public function identify(Request $request, BiometricEngineClient $engine): JsonResponse
    {
        if (! Schema::hasTable('biometric_fingerprints')) {
            return response()->json([
                'success' => false,
                'message' => 'Ejecuta php artisan migrate antes de buscar huellas biometricas.',
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

        $fingerprints = BiometricFingerprint::query()
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->whereNotNull('template_data')
            ->latest('enrolled_at')
            ->latest()
            ->get();

        if ($fingerprints->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes huellas activas registradas para buscar.',
            ], 404);
        }

        $data = $validator->validated();
        $threshold = (float) ($data['threshold'] ?? config('services.biometric_engine.threshold', 40));
        $templates = [];

        foreach ($fingerprints as $fingerprint) {
            try {
                $templates[] = [
                    'id' => $fingerprint->id,
                    'finger_position' => $fingerprint->finger_position,
                    'template' => decrypt($fingerprint->template_data),
                ];
            } catch (DecryptException) {
                continue;
            }
        }

        if ($templates === []) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo leer ningun template biometrico guardado.',
            ], 500);
        }

        try {
            $candidateTemplate = $engine->createTemplate($data['sample_image']);
            $payload = $engine->identifyTemplates($candidateTemplate['template'], $templates, $threshold);
        } catch (ConnectionException) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar con el microservicio biometrico.',
            ], 503);
        } catch (RuntimeException) {
            return response()->json([
                'success' => false,
                'message' => 'El motor biometrico no pudo buscar la huella.',
            ], 502);
        } finally {
            unset($templates, $candidateTemplate);
        }

        $best = $payload['best'] ?? [];
        $matched = (bool) ($payload['match'] ?? false);

        return response()->json([
            'success' => true,
            'message' => $matched ? 'Huella identificada.' : 'No se encontro una coincidencia confiable.',
            'data' => [
                'match' => $matched,
                'fingerprint_id' => $best['id'],
                'finger_position' => $best['finger_position'],
                'score' => $best['score'],
                'threshold' => isset($payload['threshold']) ? (float) $payload['threshold'] : $threshold,
                'candidates' => $payload['candidates'] ?? [],
            ],
        ]);
    }
}
