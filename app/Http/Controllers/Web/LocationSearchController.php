<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LocalLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationSearchController extends Controller
{
    public function __invoke(Request $request, LocalLocations $locations): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:country,city,all'],
            'country' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json([
            'data' => $locations->search(
                query: $validated['q'] ?? '',
                type: $validated['type'] ?? 'all',
                country: $validated['country'] ?? null,
                limit: (int) ($validated['limit'] ?? 20),
            ),
        ]);
    }
}
