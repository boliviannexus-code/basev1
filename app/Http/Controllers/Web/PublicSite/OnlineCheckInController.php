<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;
use TCPDF2DBarcode;

class OnlineCheckInController extends Controller
{
    public function welcome(Request $request, Company $company): View
    {
        abort_unless($company->is_active, 404);

        $language = in_array($request->query('lang'), ['es', 'en', 'pt'], true) ? $request->query('lang') : 'es';
        $choice = in_array($request->query('choice'), ['reservation', 'new'], true) ? $request->query('choice') : null;

        return view('public.online-check-in.welcome', compact('company', 'language', 'choice'));
    }

    public function qr(Request $request, Company $company): View
    {
        abort_unless(CompanyContext::belongsToUser($company->id, $request->user()), 404);
        abort_unless($company->is_active, 404);

        $url = route('public.online-check-in.welcome', $company);
        $barcode = new TCPDF2DBarcode($url, 'QRCODE,M');
        $size = $barcode->getBarcodeArray()['num_cols'] * 5;
        $svg = $barcode->getBarcodeSVGcode(5, 5);
        $svg = str_replace('<svg width=', '<svg viewBox="-20 -20 '.($size + 40).' '.($size + 40).'" width=', $svg);
        $svg = str_replace('<g id="elements"', '<rect x="-20" y="-20" width="'.($size + 40).'" height="'.($size + 40).'" fill="white"/><g id="elements"', $svg);

        return view('companies.online-check-in-qr', compact('company', 'url', 'svg'));
    }
}
