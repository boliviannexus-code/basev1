<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Reports\OperationalReportService;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use TCPDF;

class ReportController extends Controller
{
    public function __construct(
        private readonly OperationalReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $companyId = (int) $company->id;
        $isStatistics = $request->query('tab') === 'statistics';
        [$filters, $isDaily, $dailyDate] = $this->economicFilters($request);
        $reportTypes = $this->reports->reportTypes();
        $statisticsTooLong = $isStatistics && $filters['from']->startOfDay()->diffInDays($filters['to']->startOfDay()) > 365;
        $data = $isStatistics
            ? ['statistics' => $statisticsTooLong ? null : $this->reports->statistics($companyId, $filters)]
            : $this->reports->build($companyId, $filters);

        return view('reports.index', [
            ...$this->reports->options($companyId),
            ...$data,
            'filters' => $filters,
            'reportTypes' => $reportTypes,
            'reportType' => $filters['type'],
            'reportTitle' => $isStatistics ? 'Estadísticas económicas' : ($isDaily ? 'Reporte diario · '.$reportTypes[$filters['type']] : $reportTypes[$filters['type']]),
            'isStatistics' => $isStatistics,
            'statisticsTooLong' => $statisticsTooLong,
            'isDaily' => $isDaily,
            'dailyDate' => $dailyDate,
            'company' => $company,
        ]);
    }

    public function print(Request $request): Response
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $companyId = (int) $company->id;
        [$filters, $isDaily, $dailyDate] = $this->economicFilters($request);
        $reportTypes = $this->reports->reportTypes();
        $data = $this->reports->build($companyId, $filters);

        $html = view('reports.pdf', [
            ...$data,
            'company' => $company,
            'logoPath' => $this->reportLogoSource($company),
            'printedBy' => $request->user(),
            'filters' => $filters,
            'reportType' => $filters['type'],
            'reportTitle' => $isDaily ? 'Reporte diario · '.$reportTypes[$filters['type']] : $reportTypes[$filters['type']],
        ])->render();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor($company?->name ?? config('app.name'));
        $pdf->SetTitle($isDaily ? 'Reporte diario · '.$reportTypes[$filters['type']] : $reportTypes[$filters['type']]);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return response($pdf->Output('reporte.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte.pdf"',
        ]);
    }

    private function economicFilters(Request $request): array
    {
        $legacyPeriod = $request->hasAny(['from', 'to']) && ! $request->has('daily');
        $isDaily = $request->query('tab') === 'statistics' ? false : ($legacyPeriod ? false : $request->boolean('daily', true));
        $dailyDate = null;
        $input = $request->query();
        if ($isDaily) {
            $validated = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);
            $dailyDate = CarbonImmutable::parse($validated['date'] ?? today()->toDateString())->startOfDay();
            $input['from'] = $dailyDate->toDateString();
            $input['to'] = $dailyDate->toDateString();
        }

        return [$this->reports->filters($input), $isDaily, $dailyDate];
    }

    public function projected(Request $request, \App\Services\Reports\ProjectedCollectionsService $projection): View
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $filters = $request->validate(['to' => ['nullable', 'date_format:Y-m-d']]);
        $to = $filters['to'] ?? null;
        $today = today()->toDateString();
        $rows = $projection->build((int) $company->id)
            ->filter(fn (array $row): bool => $row['date'] && $row['date']->toDateString() >= $today);

        return view('reports.projected.index', [
            'rows' => $to ? $rows->filter(fn (array $row): bool => $row['date'] && $row['date']->toDateString() <= $to)->values() : $rows,
            'to' => $to,
        ]);
    }

    public function occupancy(Request $request): View
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $companyId = (int) $company->id;
        $filters = $this->reports->filters($request->except('user_id') + ['type' => 'occupancy']);
        $isDaily = $request->boolean('daily', true);
        $occupancy = $isDaily ? null : $this->reports->occupancy($companyId, $filters);
        $dailyDate = CarbonImmutable::parse($request->query('date', today()->toDateString()))->startOfDay();
        $dailyOccupancy = $isDaily ? $this->reports->dailyOccupancy($companyId, $dailyDate, $filters) : null;

        return view('reports.occupancy.index', [
            ...$this->reports->occupancyOptions($companyId),
            'filters' => $filters,
            'company' => $company,
            'occupancy' => $occupancy,
            'dailyOccupancy' => $dailyOccupancy,
            'isDaily' => $isDaily,
            'dailyDate' => $dailyDate,
            'reportTitle' => $isDaily ? 'Reporte diario de ocupabilidad' : 'Reporte de ocupabilidad',
        ]);
    }

    public function occupancyPrint(Request $request): Response
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $companyId = (int) $company->id;
        $filters = $this->reports->filters($request->except('user_id') + ['type' => 'occupancy']);
        $isDaily = $request->boolean('daily', true);
        $dailyDate = CarbonImmutable::parse($request->query('date', today()->toDateString()))->startOfDay();

        if ($isDaily) {
            $dailyOccupancy = $this->reports->dailyOccupancy($companyId, $dailyDate, $filters);

            $html = view('reports.occupancy.daily-pdf', [
                'company' => $company,
                'logoPath' => $this->reportLogoSource($company),
                'printedBy' => $request->user(),
                'filters' => $filters,
                'dailyOccupancy' => $dailyOccupancy,
                'reportTitle' => 'Reporte diario de ocupabilidad',
            ])->render();

            $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator(config('app.name'));
            $pdf->SetAuthor($company?->name ?? config('app.name'));
            $pdf->SetTitle('Reporte diario de ocupabilidad');
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 12);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');

            return response($pdf->Output('reporte-diario-ocupabilidad.pdf', 'S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="reporte-diario-ocupabilidad.pdf"',
            ]);
        }

        $occupancy = $this->reports->occupancy($companyId, $filters);

        $html = view('reports.occupancy.pdf', [
            'company' => $company,
            'logoPath' => $this->reportLogoSource($company),
            'printedBy' => $request->user(),
            'filters' => $filters,
            'occupancy' => $occupancy,
            'reportTitle' => 'Reporte de ocupabilidad',
        ])->render();

        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor($company?->name ?? config('app.name'));
        $pdf->SetTitle('Reporte de ocupabilidad');
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();
        $pdf->writeHTML($html, true, false, true, false, '');

        return response($pdf->Output('reporte-ocupabilidad.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte-ocupabilidad.pdf"',
        ]);
    }

    private function reportLogoSource($company): ?string
    {
        foreach ([$company->logo_path, $company->logo] as $path) {
            if (! $path) {
                continue;
            }

            $normalizedPath = str($path)->after('storage/')->ltrim('/')->toString();

            if (Storage::disk('public')->exists($normalizedPath)) {
                return $this->imageSourceFromPath(Storage::disk('public')->path($normalizedPath));
            }

            if (is_file($path)) {
                return $this->imageSourceFromPath($path);
            }

            $publicPath = public_path($path);
            if (is_file($publicPath)) {
                return $this->imageSourceFromPath($publicPath);
            }
        }

        return null;
    }

    private function imageSourceFromPath(string $path): ?string
    {
        if (! is_readable($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
