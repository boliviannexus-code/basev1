<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\TourResource;
use App\Http\Resources\WebsiteContentResource;
use App\Models\Tour;
use App\Services\PublicTourService;
use App\Services\WebsiteContentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicTourController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PublicTourService $tours,
        private readonly WebsiteContentService $website,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tours->search($request->only([
            'destination',
            'date',
            'people',
            'category',
            'max_price',
            'duration',
            'guide_type',
        ]));

        return $this->successResponse([
            'items' => TourResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Tour $tour): JsonResponse
    {
        return $this->successResponse(new TourResource($this->tours->findPublicTour($tour)));
    }

    public function featured(): JsonResponse
    {
        return $this->successResponse(TourResource::collection($this->tours->featuredTours()));
    }

    public function categories(): JsonResponse
    {
        return $this->successResponse(CategoryResource::collection($this->tours->categories()));
    }

    public function websiteContent(): JsonResponse
    {
        return $this->successResponse(new WebsiteContentResource([
            'settings' => $this->website->settings(),
            'featured_tours' => $this->website->featuredTours(),
            'companies' => $this->website->visibleCompanies(),
        ]));
    }
}
