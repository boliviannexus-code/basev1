<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Player;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class SportsReportPdfService
{
    public function table(string $title, ?Company $company, array $filters, array $headers, Collection $rows, string $filename, ?array $widths = null): Response
    {
        $pdf = $this->makePdf($title);
        $pdf->AddPage();
        $pdf->writeHTML($this->html($title, $company, $filters, $headers, $rows, $widths), true, false, true, false, '');

        return response($pdf->Output(Str::slug($filename).'.pdf', 'S'), 200, [
            'Content-Disposition' => 'inline; filename="'.Str::slug($filename).'.pdf"',
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function kardex(Player $player, array $context, string $filename): Response
    {
        $title = 'Kardex de jugador';
        $pdf = $this->makePdf($title);
        $pdf->AddPage();
        $pdf->writeHTML($this->kardexHtml($player, $context), true, false, true, false, '');

        return response($pdf->Output(Str::slug($filename).'.pdf', 'S'), 200, [
            'Content-Disposition' => 'inline; filename="'.Str::slug($filename).'.pdf"',
            'Content-Type' => 'application/pdf',
        ]);
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

    private function html(string $title, ?Company $company, array $filters, array $headers, Collection $rows, ?array $widths = null): string
    {
        $html = '
            '.$this->reportHeaderHtml($company, '
                <div style="font-size:8px;color:#64748b;font-weight:bold;">IMPRESION</div>
                <div style="font-size:8px;line-height:9px;color:#132f4c;">Fecha: '.e(now()->format('d/m/Y H:i')).'</div>
                <div style="font-size:8px;line-height:9px;color:#132f4c;">Usuario: '.e(auth()->user()?->name ?? '-').'</div>
            ').'
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:100%;text-align:center;color:#132f4c;font-size:12px;font-weight:bold;">
                        '.e(str($title)->upper()->toString()).'
                    </td>
                </tr>
            </table>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#0f766e;color:#ffffff;">
                <tr>
                    <td style="width:58%;font-size:8px;font-weight:bold;">
                        TORNEO: '.e(str($filters['tournament'] ?? '-')->upper()->toString()).'
                    </td>
                    <td style="width:27%;font-size:8px;font-weight:bold;text-align:center;">
                        CAT.: '.e(str($filters['category'] ?? 'Todas')->upper()->toString()).'
                    </td>
                    <td style="width:15%;font-size:8px;font-weight:bold;text-align:right;">
                        REG.: '.e((string) $rows->count()).'
                    </td>
                </tr>
            </table>
            '.$this->dateRangeHtml($filters).'
            <div style="height:4px;"></div>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <thead><tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:7px;">
        ';

        foreach ($headers as $index => $header) {
            $width = $widths[$index] ?? null;
            $style = $width ? ' style="width:'.$width.';"' : '';
            $html .= '<th'.$style.'>'.e($header).'</th>';
        }

        $html .= '</tr></thead><tbody>';

        if ($rows->isEmpty()) {
            return $html.'<tr><td colspan="'.count($headers).'" style="text-align:center;color:#667085;">Sin registros.</td></tr></tbody></table>';
        }

        foreach ($rows as $row) {
            $html .= '<tr style="font-size:7px;">';
            foreach ($row as $index => $cell) {
                $width = $widths[$index] ?? null;
                $style = $width ? ' style="width:'.$width.';"' : '';
                $html .= '<td'.$style.'>'.$this->cellHtml($cell).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    private function cellHtml(mixed $cell): string
    {
        return $cell instanceof Htmlable ? $cell->toHtml() : e((string) $cell);
    }

    private function kardexHtml(Player $player, array $context): string
    {
        $summary = $context['summary'];
        $html = '
            '.$this->reportHeaderHtml($player->company, '
                <div style="font-size:8px;color:#64748b;font-weight:bold;">IMPRESION</div>
                <div style="font-size:8px;line-height:9px;color:#132f4c;">Fecha: '.e(now()->format('d/m/Y H:i')).'</div>
                <div style="font-size:8px;line-height:9px;color:#132f4c;">Usuario: '.e(auth()->user()?->name ?? '-').'</div>
            ').'
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:100%;text-align:center;color:#132f4c;font-size:13px;font-weight:bold;">KARDEX DE JUGADOR</td>
                </tr>
            </table>
            <table cellpadding="5" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;background-color:#f8fafc;">
                <tr>
                    <td style="width:55%;font-size:8px;"><b>Jugador:</b> '.e($player->full_name).'</td>
                    <td style="width:20%;font-size:8px;"><b>Codigo:</b> '.e($player->internal_code ?? '-').'</td>
                    <td style="width:25%;font-size:8px;"><b>CI:</b> '.e($player->ci ?? '-').'</td>
                </tr>
                <tr>
                    <td style="width:35%;font-size:8px;"><b>Nacimiento:</b> '.e($player->birth_date?->format('d/m/Y') ?? '-').'</td>
                    <td style="width:20%;font-size:8px;"><b>Edad:</b> '.e((string) ($player->age() ?? '-')).'</td>
                    <td style="width:20%;font-size:8px;"><b>Estado:</b> '.e($player->is_active ? 'Activo' : 'Inactivo').'</td>
                    <td style="width:25%;font-size:8px;"><b>Liga:</b> '.e($player->company?->name ?? '-').'</td>
                </tr>
            </table>
            <div style="height:5px;"></div>
            <table cellpadding="4" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:7px;">
                    <td>Equipos</td><td>Habilit.</td><td>Partidos</td><td>Goles</td><td>Amarillas</td><td>Rojas</td><td>Pases</td><td>Castigos</td>
                </tr>
                <tr style="font-size:8px;text-align:center;">
                    <td>'.e((string) $summary['teams']).'</td>
                    <td>'.e((string) $summary['habilitations']).'</td>
                    <td>'.e((string) $summary['matches']).'</td>
                    <td>'.e((string) $summary['goals']).'</td>
                    <td>'.e((string) $summary['yellow_cards']).'</td>
                    <td>'.e((string) $summary['red_cards']).'</td>
                    <td>'.e((string) $summary['transfers']).'</td>
                    <td>'.e((string) $summary['punishments']).'</td>
                </tr>
            </table>
            <div style="height:6px;"></div>
            <div style="font-size:10px;color:#132f4c;font-weight:bold;">HISTORIAL DE EQUIPOS</div>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#e2e8f0;font-weight:bold;font-size:7px;text-align:center;">
                    <td style="width:38%;">Equipo</td><td style="width:22%;">Division</td><td style="width:18%;">Estado</td><td style="width:22%;">Fecha</td>
                </tr>
        ';

        foreach ($context['teamHistory'] as $row) {
            $html .= '
                <tr style="font-size:7px;">
                    <td style="width:38%;">'.e($row->team?->name ?? '-').'</td>
                    <td style="width:22%;">'.e($row->division?->name ?? '-').'</td>
                    <td style="width:18%;">'.e($row->status).'</td>
                    <td style="width:22%;text-align:center;">'.e($row->joined_at?->format('d/m/Y') ?? '-').'</td>
                </tr>
            ';
        }

        if ($context['teamHistory']->isEmpty()) {
            $html .= '<tr><td colspan="4" style="text-align:center;color:#667085;">Sin historial de equipos.</td></tr>';
        }

        $html .= '
            </table>
            <div style="height:6px;"></div>
            <div style="font-size:10px;color:#132f4c;font-weight:bold;">RESUMEN POR TORNEO</div>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#e2e8f0;font-weight:bold;font-size:6.5px;text-align:center;">
                    <td style="width:24%;">Torneo</td><td style="width:20%;">Equipos</td><td style="width:8%;">Hab.</td><td style="width:8%;">PJ</td><td style="width:8%;">Goles</td><td style="width:8%;">TA</td><td style="width:8%;">TR</td><td style="width:8%;">Cast.</td><td style="width:8%;">Pases</td>
                </tr>
        ';

        foreach ($context['tournamentSummary'] as $row) {
            $html .= '
                <tr style="font-size:6.5px;">
                    <td style="width:24%;">'.e($row['tournament']).'</td>
                    <td style="width:20%;">'.e($row['teams_label']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['habilitations']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['matches']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['goals']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['yellow_cards']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['red_cards']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['suspended_matches']).'</td>
                    <td style="width:8%;text-align:center;">'.e((string) $row['transfers']).'</td>
                </tr>
            ';
        }

        if ($context['tournamentSummary']->isEmpty()) {
            $html .= '<tr><td colspan="9" style="text-align:center;color:#667085;">Sin resumen por torneo.</td></tr>';
        }

        $html .= '
            </table>
            <div style="height:6px;"></div>
            <div style="font-size:10px;color:#132f4c;font-weight:bold;">DETALLES HISTORICOS</div>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;font-size:7px;text-align:center;">
                    <td style="width:14%;">Fecha</td><td style="width:16%;">Tipo</td><td style="width:18%;">Equipo</td><td style="width:20%;">Competencia</td><td style="width:20%;">Detalle</td><td style="width:12%;">Datos</td>
                </tr>
        ';

        foreach ($context['details'] as $row) {
            $html .= '
                <tr style="font-size:6.5px;">
                    <td style="width:14%;text-align:center;">'.e($row['date']?->format('d/m/Y H:i') ?? '-').'</td>
                    <td style="width:16%;">'.e($row['type']).'</td>
                    <td style="width:18%;">'.e($row['team']).'</td>
                    <td style="width:20%;">'.e($row['competition']).'</td>
                    <td style="width:20%;">'.e($row['detail']).'</td>
                    <td style="width:12%;">'.e($row['stats']).'</td>
                </tr>
            ';
        }

        if ($context['details']->isEmpty()) {
            $html .= '<tr><td colspan="6" style="text-align:center;color:#667085;">Sin detalles historicos.</td></tr>';
        }

        return $html.'</table>';
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

    private function dateRangeHtml(array $filters): string
    {
        if (blank($filters['date_from'] ?? null) && blank($filters['date_to'] ?? null)) {
            return '';
        }

        return '
            <table cellpadding="3" cellspacing="0" style="width:100%;background-color:#f8fafc;color:#132f4c;border:1px solid #d8dee9;">
                <tr>
                    <td style="width:50%;font-size:8px;">DESDE: '.e($filters['date_from'] ?? '-').'</td>
                    <td style="width:50%;font-size:8px;text-align:right;">HASTA: '.e($filters['date_to'] ?? '-').'</td>
                </tr>
            </table>
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
}
