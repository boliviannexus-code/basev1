<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourImage;
use App\Repositories\TourRepository;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourService
{
    public function __construct(
        private readonly TourRepository $tours
    ) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->tours->paginate($perPage);
    }

    public function create(array $data): Tour
    {
        return $this->tours->create($this->normalize($data));
    }

    public function update(Tour $tour, array $data): Tour
    {
        return $this->tours->update($tour, $this->normalize($data));
    }

    public function delete(Tour $tour): bool
    {
        foreach ($tour->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        return $this->tours->delete($tour);
    }

    public function companiesForSelect(): Collection
    {
        return $this->tours->companiesForSelect();
    }

    public function categoriesForSelect(): Collection
    {
        return $this->tours->categoriesForSelect();
    }

    public function guideTypesForSelect(): Collection
    {
        return $this->tours->guideTypesForSelect();
    }

    public function transportTypesForSelect(): Collection
    {
        return $this->tours->transportTypesForSelect();
    }

    public function activityTypesForSelect(): Collection
    {
        return $this->tours->activityTypesForSelect();
    }

    public function createDraft(array $data): Tour
    {
        $data = $this->normalize($data);
        $data['status'] = Tour::STATUS_DRAFT;
        $data['review_status'] = Tour::REVIEW_DRAFT;
        $data['current_step'] = max(2, (int) ($data['current_step'] ?? 2));

        $tour = $this->tours->create($data);

        if (! $tour->reference_code) {
            $tour = $this->tours->update($tour, [
                'reference_code' => $this->generateReferenceCode($tour),
            ]);
        }

        return $tour;
    }

    public function updateStep(Tour $tour, int $step, array $data): Tour
    {
        $data = $this->normalizeStepData($tour, $step, $data);
        $data['current_step'] = min(Tour::TOTAL_STEPS, max((int) $tour->current_step, min($step + 1, Tour::TOTAL_STEPS)));

        return $this->tours->update($tour, $data);
    }

    public function finalize(Tour $tour): Tour
    {
        $errors = [];

        for ($step = 1; $step <= Tour::TOTAL_STEPS; $step++) {
            $validator = Validator::make($this->dataForValidation($tour), $this->rulesForStep($step, $tour, true), [], $this->attributes());

            if ($validator->fails()) {
                $errors['step_'.$step] = ['Paso '.$step.' - '.Tour::STEPS[$step].': '.$validator->errors()->first()];
            }
        }

        if ($this->tours->imagesCount($tour) < 5) {
            $errors['step_8'] = ['Paso 8 - Imagenes: debes subir al menos 5 imagenes.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $data = [
            'status' => Tour::STATUS_DRAFT,
            'review_status' => Tour::REVIEW_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'current_step' => Tour::TOTAL_STEPS,
        ];

        if (! $tour->reference_code) {
            $data['reference_code'] = $this->generateReferenceCode($tour);
        }

        return $this->tours->update($tour, $data);
    }

    public function pendingReviewPaginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->tours->pendingReviewPaginate($perPage);
    }

    public function approve(Tour $tour, int $reviewerId): Tour
    {
        return $this->tours->update($tour, [
            'review_status' => Tour::REVIEW_APPROVED,
            'status' => Tour::STATUS_INACTIVE,
            'rejection_points' => null,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    public function toggleOperationalStatus(Tour $tour): Tour
    {
        if ($tour->review_status !== Tour::REVIEW_APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Solo se pueden habilitar o deshabilitar tours aprobados.',
            ]);
        }

        $status = $tour->status === Tour::STATUS_ACTIVE
            ? Tour::STATUS_INACTIVE
            : Tour::STATUS_ACTIVE;

        return $this->tours->update($tour, ['status' => $status]);
    }

    public function reject(Tour $tour, array $points, int $reviewerId): Tour
    {
        $history = $tour->correction_history ?? [];
        $history[] = [
            'requested_at' => now()->toIso8601String(),
            'reviewer_id' => $reviewerId,
            'points' => array_values($points),
        ];

        return $this->tours->update($tour, [
            'review_status' => Tour::REVIEW_REJECTED,
            'status' => Tour::STATUS_DRAFT,
            'rejection_points' => array_values($points),
            'correction_history' => $history,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
        ]);
    }

    public function savePrices(Tour $tour, array $prices): void
    {
        if ($tour->review_status !== Tour::REVIEW_APPROVED) {
            throw ValidationException::withMessages([
                'prices' => 'El tour debe estar aprobado antes de configurar precios.',
            ]);
        }

        $normalized = collect($prices)
            ->map(fn (array $price): array => [
                'title' => filled($price['title'] ?? null) ? trim((string) $price['title']) : null,
                'min_people' => (int) $price['min_people'],
                'max_people' => isset($price['max_people']) && $price['max_people'] !== '' ? (int) $price['max_people'] : null,
                'price_usd' => number_format((float) $price['price_usd'], 2, '.', ''),
            ])
            ->sortBy('min_people')
            ->values()
            ->all();

        $this->tours->replacePrices($tour, $normalized);
    }

    public function storeImages(Tour $tour, array $images): void
    {
        foreach ($images as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $path = $image->store('tours/'.$tour->id, 'public');
            $isFirst = $this->tours->imagesCount($tour) === 0;

            $this->tours->createImage($tour, [
                'path' => $path,
                'original_name' => $image->getClientOriginalName(),
                'is_main' => $isFirst,
                'sort_order' => $this->tours->imagesCount($tour) + 1,
            ]);
        }
    }

    public function deleteImage(TourImage $image): void
    {
        Storage::disk('public')->delete($image->path);
        $wasMain = $image->is_main;
        $tour = $image->tour;
        $image->delete();

        if ($wasMain && $tour->images()->exists()) {
            $tour->images()->oldest('id')->first()?->update(['is_main' => true]);
        }
    }

    public function setMainImage(TourImage $image): void
    {
        $image->tour->images()->update(['is_main' => false]);
        $image->update(['is_main' => true]);
    }

    public function userCanAccess(Tour $tour): bool
    {
        $user = auth()->user();

        return $user !== null && CompanyContext::belongsToUser((int) $tour->company_id, $user);
    }

    private function normalize(array $data): array
    {
        $data = CompanyContext::applyToData($data);
        $data['status'] = $data['status'] ?? Tour::STATUS_DRAFT;

        if (($data['title'] ?? null) && empty($data['name'])) {
            $data['name'] = $data['title'];
        }

        return $data;
    }

    public function rulesForStep(int $step, ?Tour $tour = null, bool $final = false): array
    {
        $companyId = $tour?->company_id ?? CompanyContext::id();
        $tourId = $tour?->id;

        return match ($step) {
            1 => [
                'company_id' => CompanyContext::id() === null && $tour === null ? ['required', 'integer', 'exists:companies,id'] : ['nullable'],
                'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
                'title' => ['required', 'string', 'max:255', Rule::unique('tours', 'title')->where('company_id', $companyId)->ignore($tourId)],
                'reference_code' => ['nullable', 'string', 'max:120', Rule::unique('tours', 'reference_code')->where('company_id', $companyId)->ignore($tourId)],
            ],
            2 => [
                'short_description' => ['required', 'string', 'min:200', 'max:1000'],
                'full_description' => ['required', 'string', 'min:500', 'max:2000'],
            ],
            3 => [
                'country' => ['required', 'string', 'max:120'],
                'city' => ['required', 'string', 'max:120'],
            ],
            4 => [
                'keywords' => ['required', 'array', 'min:5', 'max:20'],
                'keywords.*' => ['required', 'string', 'max:60'],
            ],
            5 => [
                'includes' => ['required', 'string', 'max:5000'],
                'excludes' => ['required', 'string', 'max:5000'],
            ],
            6 => [
                'guide_type_id' => ['required', 'integer', 'exists:guide_types,id'],
                'includes_food' => ['required', 'boolean'],
                'food_details' => ['nullable', 'required_if:includes_food,1', 'string', 'max:2000'],
                'includes_transport' => ['required', 'boolean'],
                'transport_type_id' => ['nullable', 'required_if:includes_transport,1', 'integer', 'exists:transport_types,id'],
            ],
            7 => [
                'pets_allowed' => ['required', 'boolean'],
                'prohibitions' => ['nullable', 'string', 'max:2000'],
                'recommendations' => ['nullable', 'string', 'max:2000'],
                'emergency_phone' => ['required', 'string', 'max:80'],
            ],
            8 => $final ? [] : [
                'images' => [$final ? 'nullable' : 'nullable', 'array'],
                'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            ],
            9 => $final ? [
                'activity_type' => ['required', 'string', Rule::in(['private', 'shared'])],
                'meeting_point' => ['required', 'string', 'max:255'],
                'booking_deadline_value' => ['required', 'integer', 'min:1', 'max:720'],
                'booking_deadline_unit' => ['required', 'string', Rule::in(['hours', 'days'])],
                'capacity' => ['required', 'integer', 'min:1', 'max:99999'],
            ] : [
                'activity_type' => ['required', 'string', Rule::in(['private', 'shared'])],
                'meeting_point' => ['required', 'string', 'max:255'],
                'booking_deadline_value' => ['required', 'integer', 'min:1', 'max:720'],
                'booking_deadline_unit' => ['required', 'string', Rule::in(['hours', 'days'])],
                'capacity' => ['required', 'integer', 'min:1', 'max:99999'],
            ],
            10 => [
                'itinerary_days' => ['required', 'array', 'min:1', 'max:30'],
                'itinerary_days.*.day_number' => ['required', 'integer', 'min:1', 'max:30'],
                'itinerary_days.*.title' => ['required', 'string', 'max:255'],
                'itinerary_days.*.summary' => ['nullable', 'string', 'max:2000'],
                'itinerary_days.*.stops' => ['required', 'array', 'min:1', 'max:50'],
                'itinerary_days.*.stops.*.activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')->where('is_active', true)],
                'itinerary_days.*.stops.*.position' => ['required', 'integer', 'min:1', 'max:50'],
                'itinerary_days.*.stops.*.start_time' => ['nullable', 'date_format:H:i'],
                'itinerary_days.*.stops.*.title' => ['required', 'string', 'max:255'],
                'itinerary_days.*.stops.*.location_name' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    private function normalizeStepData(Tour $tour, int $step, array $data): array
    {
        if ($step === 1) {
            $data['name'] = $data['title'];
            $data['reference_code'] = $data['reference_code'] ?: $this->generateReferenceCode($tour);
        }

        if ($tour->review_status === Tour::REVIEW_REJECTED) {
            $data['review_status'] = Tour::REVIEW_DRAFT;
        }

        if ($step === 2) {
            $data['description'] = $data['short_description'];
        }

        if ($step === 4 && is_string($data['keywords'] ?? null)) {
            $data['keywords'] = $this->parseKeywords($data['keywords']);
        }

        if ($step === 5) {
            $data['included'] = $data['includes'] ?? null;
            $data['not_included'] = $data['excludes'] ?? null;
        }

        if ($step === 6) {
            $data['includes_food'] = (bool) ($data['includes_food'] ?? false);
            if (! $data['includes_food']) {
                $data['food_details'] = null;
            }
            $data['includes_transport'] = (bool) ($data['includes_transport'] ?? false);
            if (! $data['includes_transport']) {
                $data['transport_type_id'] = null;
            }
        }

        if ($step === 7) {
            $data['pets_allowed'] = (bool) ($data['pets_allowed'] ?? false);
            $data['pets_policy'] = $data['pets_allowed'] ? 'Permitidas' : 'No permitidas';
        }

        if ($step === 8) {
            unset($data['images']);
        }

        if ($step === 10) {
            $this->replaceItinerary($tour, $data['itinerary_days'] ?? []);
            unset($data['itinerary_days']);
        }

        return $data;
    }

    private function replaceItinerary(Tour $tour, array $days): void
    {
        $normalized = collect($days)
            ->sortBy(fn (array $day): int => (int) ($day['day_number'] ?? 0))
            ->values()
            ->map(function (array $day, int $dayIndex): array {
                $stops = collect($day['stops'] ?? [])
                    ->sortBy(fn (array $stop): int => (int) ($stop['position'] ?? 0))
                    ->values()
                    ->map(fn (array $stop, int $stopIndex): array => [
                        'activity_type_id' => (int) $stop['activity_type_id'],
                        'position' => $stopIndex + 1,
                        'start_time' => filled($stop['start_time'] ?? null) ? $stop['start_time'] : null,
                        'title' => trim((string) $stop['title']),
                        'location_name' => filled($stop['location_name'] ?? null) ? trim((string) $stop['location_name']) : null,
                    ])
                    ->all();

                return [
                    'day_number' => $dayIndex + 1,
                    'title' => trim((string) $day['title']),
                    'summary' => filled($day['summary'] ?? null) ? trim((string) $day['summary']) : null,
                    'stops' => $stops,
                ];
            })
            ->all();

        DB::transaction(function () use ($tour, $normalized): void {
            $this->tours->replaceItinerary($tour, $normalized);
        });
    }

    private function dataForValidation(Tour $tour): array
    {
        return array_merge($tour->toArray(), [
            'keywords' => $tour->keywords ?? [],
            'includes' => $tour->includes ?: $tour->included,
            'excludes' => $tour->excludes ?: $tour->not_included,
            'includes_food' => $tour->includes_food ? 1 : 0,
            'includes_transport' => $tour->includes_transport ? 1 : 0,
            'pets_allowed' => $tour->pets_allowed ? 1 : 0,
            'bookings_enabled' => $tour->bookings_enabled ? 1 : 0,
            'start_time' => $tour->start_time?->format('H:i'),
            'end_time' => $tour->end_time?->format('H:i'),
            'itinerary_days' => $this->itineraryForValidation($tour),
        ]);
    }

    private function itineraryForValidation(Tour $tour): array
    {
        $tour->loadMissing('itineraryDays.stops');

        return $tour->itineraryDays
            ->map(fn ($day): array => [
                'day_number' => $day->day_number,
                'title' => $day->title,
                'summary' => $day->summary,
                'stops' => $day->stops
                    ->map(fn ($stop): array => [
                        'activity_type_id' => $stop->activity_type_id,
                        'position' => $stop->position,
                        'start_time' => $stop->start_time?->format('H:i'),
                        'title' => $stop->title,
                        'location_name' => $stop->location_name,
                    ])
                    ->all(),
            ])
            ->all();
    }

    private function generateReferenceCode(Tour $tour): string
    {
        return implode('-', [
            'TOUR',
            (int) $tour->company_id,
            (int) ($tour->category_id ?: 0),
            (int) $tour->id,
        ]);
    }

    private function parseKeywords(string $keywords): array
    {
        return collect(explode(',', $keywords))
            ->map(fn (string $keyword): string => trim($keyword))
            ->filter()
            ->unique(fn (string $keyword): string => mb_strtolower($keyword))
            ->values()
            ->all();
    }

    private function attributes(): array
    {
        return [
            'category_id' => 'categoria',
            'title' => 'titulo',
            'reference_code' => 'codigo de referencia',
            'short_description' => 'descripcion breve',
            'full_description' => 'descripcion completa',
            'guide_type_id' => 'tipo de guia',
            'transport_type_id' => 'tipo de transporte',
            'emergency_phone' => 'telefono de emergencia',
            'activity_type' => 'tipo de actividad',
            'meeting_point' => 'punto de recogida',
            'booking_deadline_value' => 'tiempo limite de reserva',
            'booking_deadline_unit' => 'unidad del tiempo limite',
            'capacity' => 'cantidad de cupos',
            'itinerary_days' => 'itinerario',
            'itinerary_days.*.title' => 'titulo del dia',
            'itinerary_days.*.stops' => 'paradas del dia',
            'itinerary_days.*.stops.*.activity_type_id' => 'tipo de actividad',
            'itinerary_days.*.stops.*.title' => 'titulo de la parada',
        ];
    }
}
