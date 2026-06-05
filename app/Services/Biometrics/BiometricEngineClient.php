<?php

namespace App\Services\Biometrics;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BiometricEngineClient
{
    public function createTemplate(string $image): array
    {
        $response = $this->post('/templates', [
            'image' => $image,
        ]);

        return $response->json();
    }

    public function compareTemplates(string $storedTemplate, string $candidateTemplate, float $threshold): array
    {
        $response = $this->post('/compare-templates', [
            'stored_template' => $storedTemplate,
            'candidate_template' => $candidateTemplate,
            'threshold' => $threshold,
        ]);

        return $response->json();
    }

    public function identifyTemplates(string $candidateTemplate, array $templates, float $threshold): array
    {
        $response = $this->post('/identify-templates', [
            'candidate_template' => $candidateTemplate,
            'templates' => $templates,
            'threshold' => $threshold,
        ], 60);

        return $response->json();
    }

    /**
     * @throws ConnectionException
     */
    private function post(string $path, array $payload, int $timeout = 15): Response
    {
        $engineUrl = rtrim((string) config('services.biometric_engine.url'), '/');

        if ($engineUrl === '') {
            throw new RuntimeException('Configura BIOMETRIC_ENGINE_URL antes de usar biometria.');
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->post($engineUrl.$path, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('El motor biometrico no pudo procesar la solicitud.', $response->status());
        }

        return $response;
    }
}
