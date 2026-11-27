<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\OnlineCheckInSubmission;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Support\CompanyContext;
use App\Support\CompanyCurrency;
use App\Support\OnlineCheckInEligibility;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use TCPDF2DBarcode;

class OnlineCheckInController extends Controller
{
    public function welcome(Request $request, Company $company): View
    {
        abort_unless($company->is_active, 404);

        $language = in_array($request->query('lang'), ['es', 'en', 'pt', 'fr'], true) ? $request->query('lang') : 'es';
        $choice = in_array($request->query('choice'), ['reservation', 'new'], true) ? $request->query('choice') : null;

        return view('public.online-check-in.welcome', compact('company', 'language', 'choice'));
    }

    public function search(Request $request, Company $company): View
    {
        abort_unless($company->is_active, 404);

        $language = in_array($request->input('lang'), ['es', 'en', 'pt', 'fr'], true) ? $request->input('lang') : 'es';
        $name = is_string($request->input('name')) ? trim($request->input('name')) : '';
        $choice = 'reservation';
        $searched = true;
        $invalidName = mb_strlen($name) < 3 || mb_strlen($name) > 100;
        $results = collect();

        if (! $invalidName) {
            $terms = preg_split('/\s+/u', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);
            $find = function ($query) use ($company, $terms) {
                $query->forCompany($company->id)->whereDate('check_out', '>=', today()->toDateString());
                foreach ($terms as $term) {
                    $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                    $query->whereRaw("LOWER(guest_name) LIKE ? ESCAPE '!'", [$pattern]);
                }

                return $query->orderBy('check_in')->limit(20)->get();
            };

            $groups = $find(OnlineCheckInEligibility::groups(ReservationGroup::query(), $company->id));
            $individual = $find(OnlineCheckInEligibility::reservations(Reservation::query()->whereNull('reservation_group_id'), $company->id));
            $groups->load(['reservations' => fn ($query) => OnlineCheckInEligibility::reservations($query->getQuery()->forCompany($company->id), $company->id)]);
            $reservations = new Collection($groups->flatMap->reservations->concat($individual)->all());
            $reservations->load([
                'space' => fn ($query) => $query->forCompany($company->id),
                'room' => fn ($query) => $query->forCompany($company->id),
                'room.beds' => fn ($query) => $query->forCompany($company->id),
                'room.beds.bedType',
                'roomItems.room' => fn ($query) => $query->forCompany($company->id),
                'roomItems.room.beds' => fn ($query) => $query->forCompany($company->id),
                'roomItems.room.beds.bedType',
                'bedUnitItems.bedUnit' => fn ($query) => $query->forCompany($company->id),
                'bedUnitItems.bedUnit.bedType',
            ]);
            $results = $groups->concat($individual)->sortBy('check_in')->take(20);
            $results->each(function ($result): void {
                $reservations = $result instanceof ReservationGroup ? $result->reservations : collect([$result]);
                $result->spaces = $reservations->map(fn ($reservation) => $reservation->space?->title ?: $reservation->space?->name)->filter()->unique()->values();
                $result->bed_types = $reservations->flatMap(function ($reservation) {
                    $bedTypes = $reservation->bedUnitItems->toBase()->map(fn ($item) => $item->bedUnit?->bedType?->name);
                    $rooms = $reservation->roomItems->pluck('room')->filter();
                    if ($rooms->isEmpty() && $reservation->room) {
                        $rooms->push($reservation->room);
                    }

                    return $bedTypes->merge($rooms->flatMap(fn ($room) => $room->beds->map(fn ($bed) => $bed->bedType?->name)));
                })->filter()->unique()->values();
            });
        }

        return view('public.online-check-in.welcome', compact('company', 'language', 'choice', 'searched', 'invalidName', 'results', 'name'));
    }

    public function form(Request $request, Company $company): View
    {
        [$reservation, $language] = $this->reservationFromToken($request, $company);
        $countries = Country::query()->forCompany($company->id)->forCheckInSearch()->get();
        $rate = ExchangeRate::currentRateForCompany($company->id);
        $rate = $rate > 0 ? $rate : null;
        $total = (float) $reservation->total_amount;
        $currency = $reservation->currency;
        $local = $currency === 'USD' ? ($rate ? round($total * $rate, 2) : null) : $total;
        $usd = $currency === 'USD' ? $total : ($rate ? round($total / $rate, 2) : null);
        $snapshot = Crypt::encryptString(json_encode([
            'company' => $company->id, 'kind' => $reservation instanceof ReservationGroup ? 'group' : 'single',
            'id' => $reservation->id, 'checkout' => $reservation->check_out->toDateString(), 'total' => $total,
            'currency' => $currency, 'rate' => $rate, 'usd' => $usd, 'local' => $local,
            'local_currency' => CompanyCurrency::code($company),
        ], JSON_THROW_ON_ERROR));

        return view('public.online-check-in.form', compact('company', 'reservation', 'language', 'countries', 'rate', 'local', 'usd', 'snapshot'));
    }

    public function submit(Request $request, Company $company): RedirectResponse|View
    {
        [$reservation, $language, $snapshot] = $this->reservationFromToken($request, $company);
        abort_unless(isset($snapshot['checkout'], $snapshot['total'], $snapshot['currency'], $snapshot['local_currency']), 422);
        abort_unless($snapshot['checkout'] === $reservation->check_out->toDateString(), 409);

        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:60'],
            'country_id' => ['required', 'integer', Rule::exists('countries', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->where('is_active', true)->whereNull('deleted_at'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60'],
            'confirm_details' => ['accepted'],
        ]);
        if ($validator->fails()) {
            return $this->form($request, $company)->withErrors($validator);
        }
        $data = $validator->validated();
        OnlineCheckInSubmission::create([
            'company_id' => $company->id,
            'reservation_id' => $reservation instanceof Reservation ? $reservation->id : null,
            'reservation_group_id' => $reservation instanceof ReservationGroup ? $reservation->id : null,
            'guest_name' => $reservation->guest_name,
            'document_number' => trim($data['document_number']), 'country_id' => $data['country_id'],
            'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null,
            'confirmed_checkout' => $snapshot['checkout'], 'confirmed_price' => $snapshot['total'], 'price_currency' => $snapshot['currency'],
            'exchange_rate' => $snapshot['rate'], 'price_usd' => $snapshot['usd'], 'price_local' => $snapshot['local'], 'local_currency' => $snapshot['local_currency'],
        ]);

        return redirect()->route('public.online-check-in.welcome', ['company' => $company->public_slug, 'lang' => $language, 'sent' => 1]);
    }

    private function reservationFromToken(Request $request, Company $company): array
    {
        abort_unless($company->is_active, 404);
        try {
            $payload = json_decode(Crypt::decryptString((string) $request->input('token')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            abort(404);
        }
        abort_unless(is_array($payload) && ($payload['company'] ?? null) === $company->id && in_array($payload['kind'] ?? null, ['group', 'single'], true), 404);
        $query = $payload['kind'] === 'group' ? ReservationGroup::query() : Reservation::query()->whereNull('reservation_group_id');
        $query = $payload['kind'] === 'group'
            ? OnlineCheckInEligibility::groups($query, $company->id)
            : OnlineCheckInEligibility::reservations($query, $company->id);
        $reservation = $query->forCompany($company->id)->whereDate('check_out', '>=', today()->toDateString())->findOrFail($payload['id'] ?? null);
        $language = in_array($request->input('lang'), ['es', 'en', 'pt', 'fr'], true) ? $request->input('lang') : 'es';

        return [$reservation, $language, $payload];
    }

    public function qr(Request $request, Company $company): View
    {
        abort_unless(CompanyContext::belongsToUser($company->id, $request->user()), 404);
        abort_unless($company->is_active, 404);

        $baseUrl = config('app.online_check_in_base_url');
        $url = $baseUrl
            ? rtrim($baseUrl, '/').route('public.online-check-in.welcome', ['company' => $company->public_slug], false)
            : route('public.online-check-in.welcome', ['company' => $company->public_slug]);
        $barcode = new TCPDF2DBarcode($url, 'QRCODE,M');
        $size = $barcode->getBarcodeArray()['num_cols'] * 5;
        $svg = $barcode->getBarcodeSVGcode(5, 5);
        $svg = str_replace('<svg width=', '<svg viewBox="-20 -20 '.($size + 40).' '.($size + 40).'" width=', $svg);
        $svg = str_replace('<g id="elements"', '<rect x="-20" y="-20" width="'.($size + 40).'" height="'.($size + 40).'" fill="white"/><g id="elements"', $svg);

        return view('companies.online-check-in-qr', compact('company', 'url', 'svg'));
    }
}
