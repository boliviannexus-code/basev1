<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Meeting;
use App\Models\MeetingAttendance;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class MeetingPdfReportService
{
    public function attendance(Meeting $meeting): Response
    {
        $pdf = $this->makePdf('Asistencia reunion '.$meeting->meeting_date?->format('Y-m-d'));
        $pdf->AddPage();
        $pdf->writeHTML($this->html($meeting), true, false, true, false, '');

        return $this->pdfResponse($pdf, $this->filename($meeting));
    }

    private function makePdf(string $title): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name', 'Nexgol'));
        $pdf->SetAuthor(config('app.name', 'Nexgol'));
        $pdf->SetTitle($title);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 8);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 8);

        return $pdf;
    }

    private function pdfResponse(TCPDF $pdf, string $filename): Response
    {
        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function html(Meeting $meeting): string
    {
        $total = $meeting->attendances->count();
        $present = $meeting->attendances->where('present', true)->count();
        $permission = $meeting->attendances->where('permission_requested', true)->count();
        $absent = max(0, $total - $present - $permission);
        $dateLabel = $meeting->meeting_date
            ? str($meeting->meeting_date->copy()->locale('es')->translatedFormat('l j \d\e F Y'))->upper()->toString()
            : '-';

        return $this->reportHeaderHtml($meeting->company, '
            <div style="font-size:8px;color:#64748b;font-weight:bold;">REPORTE</div>
            <div style="font-size:19px;line-height:21px;color:#132f4c;font-weight:bold;">ASISTENCIA</div>
        ').'
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:100%;text-align:center;color:#132f4c;font-size:12px;font-weight:bold;">
                        CONTROL DE ASISTENCIA A REUNION
                    </td>
                </tr>
            </table>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#0f766e;color:#ffffff;">
                <tr>
                    <td style="width:50%;font-size:9px;font-weight:bold;">'.e(str($meeting->title)->upper()->toString()).'</td>
                    <td style="width:50%;font-size:9px;font-weight:bold;text-align:right;">'.e($dateLabel).'</td>
                </tr>
            </table>
            <div style="height:4px;"></div>
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:25%;background-color:#eef6ff;color:#132f4c;text-align:center;font-weight:bold;">TOTAL<br><span style="font-size:15px;">'.e((string) $total).'</span></td>
                    <td style="width:25%;background-color:#ecfdf5;color:#166534;text-align:center;font-weight:bold;">PRESENTES<br><span style="font-size:15px;">'.e((string) $present).'</span></td>
                    <td style="width:25%;background-color:#fffbeb;color:#92400e;text-align:center;font-weight:bold;">PERMISOS<br><span style="font-size:15px;">'.e((string) $permission).'</span></td>
                    <td style="width:25%;background-color:#fef2f2;color:#991b1b;text-align:center;font-weight:bold;">AUSENTES<br><span style="font-size:15px;">'.e((string) $absent).'</span></td>
                </tr>
            </table>
            <div style="height:4px;"></div>
            '.$this->attendanceTableHtml($meeting).'
            '.$this->footerHtml($meeting);
    }

    private function attendanceTableHtml(Meeting $meeting): string
    {
        $html = '
            <table cellpadding="4" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead>
                    <tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:8px;">
                        <th style="width:8%;">NRO</th>
                        <th style="width:52%;text-align:left;">EQUIPO</th>
                        <th style="width:15%;">ASISTENCIA</th>
                        <th style="width:15%;">HORA</th>
                        <th style="width:10%;">PERMISO</th>
                    </tr>
                </thead>
                <tbody>
        ';

        foreach ($meeting->attendances as $index => $attendance) {
            /** @var MeetingAttendance $attendance */
            $present = $attendance->present;
            $permission = $attendance->permission_requested;
            $status = $present ? 'PRESENTE' : ($permission ? 'PERMISO' : 'AUSENTE');
            $color = $present ? '#15803d' : ($permission ? '#92400e' : '#dc2626');
            $time = $present
                ? $attendance->attended_at?->format('H:i')
                : $attendance->permission_requested_at?->format('H:i');
            $html .= '
                <tr style="font-size:8px;background-color:'.($present ? '#ffffff' : '#fff7ed').';">
                    <td style="width:8%;text-align:center;">'.e((string) ($index + 1)).'</td>
                    <td style="width:52%;font-weight:bold;">'.e($attendance->team?->name ?? '-').'<br><span style="font-size:7px;color:#64748b;">'.e($this->categoryLabel($attendance)).'</span></td>
                    <td style="width:15%;text-align:center;color:'.$color.';font-weight:bold;">'.$status.'</td>
                    <td style="width:15%;text-align:center;">'.e($time ?? '-').'</td>
                    <td style="width:10%;text-align:center;">'.($permission ? 'SI' : '-').'</td>
                </tr>
            ';
        }

        return $html.'
                </tbody>
            </table>
        ';
    }

    private function categoryLabel(MeetingAttendance $attendance): string
    {
        $categories = $attendance->represented_categories;

        if ($categories) {
            return $categories;
        }

        $registration = $attendance->tournamentRegistration;

        if (! $registration) {
            return 'Sin categoria';
        }

        return trim(($registration->category?->name ?? 'Sin categoria').' · '.$registration->seriesLabel().' · '.($registration->tournament?->name ?? '-'));
    }

    private function reportHeaderHtml(?Company $company, string $rightHtml = ''): string
    {
        $companyName = $company?->name ?? config('app.name', 'Nexgol');
        $foundation = $company?->foundation_date
            ? 'Fundado el '.$company->foundation_date->copy()->locale('es')->translatedFormat('d \d\e F Y')
            : '';
        $legalPersonality = $company?->legal_personality
            ? 'Personeria juridica '.$company->legal_personality
            : '';

        return '
            <table cellpadding="0" cellspacing="0" style="width:100%;border-bottom:3px solid #132f4c;">
                <tr>
                    <td style="width:19%;padding:5px 0;text-align:left;">'.$this->logoHtml($company).'</td>
                    <td style="width:56%;padding:7px 0 13px 0;text-align:center;">
                        <div style="font-size:17px;line-height:19px;color:#132f4c;font-weight:bold;">'.e($companyName).'</div>
                        <div style="font-size:8px;line-height:8px;color:#64748b;">'.e($foundation).'</div>
                        <div style="font-size:8px;line-height:8px;color:#64748b;">'.e($legalPersonality).'</div>
                    </td>
                    <td style="width:25%;padding:7px 0;text-align:right;">'.$rightHtml.'</td>
                </tr>
            </table>
            <div style="height:5px;"></div>
        ';
    }

    private function logoHtml(?Company $company): string
    {
        if ($company?->logo_display_url) {
            return '<img src="'.$company->logo_display_url.'" style="height:24mm;max-width:32mm;">';
        }

        return '
            <table cellpadding="4" cellspacing="0" style="width:31mm;height:22mm;border:1px solid #cbd5e1;background-color:#f8fafc;">
                <tr>
                    <td style="text-align:center;color:#64748b;font-size:8px;">LOGO</td>
                </tr>
            </table>
        ';
    }

    private function footerHtml(Meeting $meeting): string
    {
        return '
            <div style="height:6px;"></div>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#132f4c;color:#ffffff;">
                <tr>
                    <td style="width:50%;font-size:7px;">Iniciada por: '.e($meeting->creator?->name ?? '-').'</td>
                    <td style="width:50%;font-size:7px;text-align:right;">Finalizada por: '.e($meeting->finisher?->name ?? '-').'</td>
                </tr>
            </table>
        ';
    }

    private function filename(Meeting $meeting): string
    {
        return (Str::slug('asistencia reunion '.$meeting->meeting_date?->format('Y-m-d').' '.$meeting->title) ?: 'asistencia-reunion').'.pdf';
    }
}
