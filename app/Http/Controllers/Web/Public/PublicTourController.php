<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Services\PublicTourService;
use App\Services\WebsiteContentService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicTourController extends Controller
{
    public function __construct(
        private readonly PublicTourService $tours,
        private readonly WebsiteContentService $website,
    ) {}

    public function home(Request $request): View
    {
        return view('public.home', [
            'websiteSettings' => $this->website->settings(),
            'featuredTours' => $this->website->featuredTours()->whenEmpty(fn () => $this->tours->featuredTours()),
            'companies' => $this->website->visibleCompanies(),
            'categories' => $this->tours->categories()->take(8),
            'search' => $request->only(['destination', 'date', 'start_date', 'end_date', 'people']),
        ]);
    }

    public function index(Request $request): View
    {
        $filters = $request->only(['destination', 'date', 'start_date', 'end_date', 'people', 'category', 'max_price', 'duration']);

        return view('public.tours.index', [
            'tours' => $this->tours->search($filters),
            'categories' => $this->tours->categories(),
            'filters' => $filters,
        ]);
    }

    public function show(Tour $tour): View
    {
        return view('public.tours.show', [
            'tour' => $this->tours->findPublicTour($tour),
        ]);
    }
}
