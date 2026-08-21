<?php

namespace App\Services;

use App\Models\Company;
use App\Models\StandingAdjustment;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class StandingPdfReportService
{
    public function standings(array $context): Response
    {
        /** @var Tournament $tournament */
        $tournament = $context['tournament'];
        $tournament->loadMissing(['company', 'season']);

        $pdf = $this->makePdf('Tabla de posiciones '.$tournament->name);
        $pdf->AddPage();
        $pdf->writeHTML($this->reportHtml($context), true, false, true, false, '');

        return $this->pdfResponse($pdf, $this->filename($tournament, $context['group']));
    }

    public function teamMatches(array $context): Response
    {
        /** @var Tournament $tournament */
        $tournament = $context['tournament'];
        /** @var Team $team */
        $team = $context['team'];
        $tournament->loadMissing(['company', 'season']);

        $pdf = $this->makePdf('Partidos de '.$team->name, 'P');
        $pdf->AddPage();
        $pdf->writeHTML($this->teamMatchesHtml($context), true, false, true, false, '');

        $filename = Str::slug('partidos '.$team->name.' '.$tournament->name) ?: 'partidos-equipo';

        return $this->pdfResponse($pdf, $filename.'.pdf');
    }

    private function teamMatchesHtml(array $context): string
    {
        $tournament = $context['tournament'];
        $team = $context['team'];
        $matches = $context['matches'];
        $rows = '';

        foreach ($matches as $match) {
            $finished = in_array($match->report?->status, ['completed', 'walkover'], true);
            $score = $finished
                ? $match->report->home_score.' - '.$match->report->away_score
                : 'PENDIENTE';
            $status = $finished
                ? ($match->report->status === 'walkover' ? 'FINALIZADO W.O.' : 'FINALIZADO')
                : ($match->matchday_date_id ? 'PROGRAMADO' : 'POR PROGRAMAR');
            $date = $match->matchdayDate?->date?->format('d/m/Y') ?? '-';
            $time = $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-';
            $round = $match->round_number ? 'Fecha '.$match->round_number : $match->stage;

            $rows .= '
                <tr style="font-size:8px;">
                    <td style="width:8%;text-align:center;">'.e((string) $match->match_number).'</td>
                    <td style="width:14%;">'.e($round).'</td>
                    <td style="width:11%;text-align:center;">'.e($date).'<br>'.e($time).'</td>
                    <td style="width:20%;text-align:right;font-weight:bold;">'.e($match->homeTeam?->name ?? $match->home_seed ?? 'Por definir').'</td>
                    <td style="width:10%;text-align:center;font-weight:bold;color:'.($finished ? '#132f4c' : '#b45309').';">'.e($score).'</td>
                    <td style="width:20%;font-weight:bold;">'.e($match->awayTeam?->name ?? $match->away_seed ?? 'Por definir').'</td>
                    <td style="width:17%;text-align:center;">'.e($status).'</td>
                </tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="7" style="text-align:center;color:#64748b;">Este equipo aun no tiene partidos generados.</td></tr>';
        }

        return $this->reportHeaderHtml($tournament->company, '
                <div style="font-size:8px;color:#64748b;font-weight:bold;">REPORTE</div>
                <div style="font-size:16px;color:#132f4c;font-weight:bold;">PARTIDOS DEL EQUIPO</div>
            ').'
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#0f766e;color:#ffffff;">
                <tr>
                    <td style="width:45%;font-size:10px;font-weight:bold;">'.e(str($team->name)->upper()->toString()).'</td>
                    <td style="width:35%;font-size:9px;text-align:center;">'.e(str($tournament->name)->upper()->toString()).'</td>
                    <td style="width:20%;font-size:9px;text-align:right;">'.e(str($context['category']?->name.' · '.$context['series'])->upper()->toString()).'</td>
                </tr>
            </table>
            <div style="height:4px;"></div>
            <table cellpadding="4" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead><tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:8px;">
                    <th style="width:8%;">#</th><th style="width:14%;">ETAPA</th><th style="width:11%;">FECHA/HORA</th>
                    <th style="width:20%;">LOCAL</th><th style="width:10%;">RESULTADO</th><th style="width:20%;">VISITANTE</th><th style="width:17%;">ESTADO</th>
                </tr></thead><tbody>'.$rows.'</tbody>
            </table>
            '.$this->reportFooterHtml($tournament->company);
    }

    private function makePdf(string $title, string $orientation = 'L'): TCPDF
    {
        $pdf = new TCPDF($orientation, 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name', 'Nexgol'));
        $pdf->SetAuthor(config('app.name', 'Nexgol'));
        $pdf->SetTitle($title);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 8);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 7);

        return $pdf;
    }

    private function pdfResponse(TCPDF $pdf, string $filename): Response
    {
        return response($pdf->Output($filename, 'S'), 200, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function reportHtml(array $context): string
    {
        /** @var Tournament $tournament */
        $tournament = $context['tournament'];
        $group = $context['group'];
        $seasonName = $tournament->season?->name ?? '-';

        return $this->reportHeaderHtml($tournament->company, '
            <div style="font-size:8px;color:#64748b;font-weight:bold;">REPORTE</div>
            <div style="font-size:19px;line-height:21px;color:#132f4c;font-weight:bold;">POSICIONES</div>
        ').'
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:100%;text-align:center;color:#132f4c;font-size:12px;font-weight:bold;">
                        TABLA DE POSICIONES GESTION '.e(str($seasonName)->upper()->toString()).'
                    </td>
                </tr>
            </table>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#0f766e;color:#ffffff;">
                <tr>
                    <td style="width:45%;font-size:9px;font-weight:bold;">TORNEO: '.e(str($tournament->name)->upper()->toString()).'</td>
                    <td style="width:35%;font-size:9px;font-weight:bold;text-align:center;">'.e(str($group['label'])->upper()->toString()).'</td>
                    <td style="width:20%;font-size:9px;font-weight:bold;text-align:right;">EQUIPOS: '.e((string) $context['standings']->count()).'</td>
                </tr>
            </table>
            <div style="height:3px;"></div>
            '.$this->standingsTableHtml($context['standings']).'
            '.$this->adjustmentsHtml($context['adjustments']).'
            '.$this->topScorersHtml($context['topScorers']).'
            '.$this->reportFooterHtml($tournament->company);
    }

    private function standingsTableHtml(Collection $standings): string
    {
        $html = '
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead>
                    <tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:7px;">
                        <th style="width:5%;">POS</th>
                        <th style="width:25%;text-align:left;">EQUIPO</th>
                        <th style="width:5%;">PJ</th>
                        <th style="width:5%;">PG</th>
                        <th style="width:5%;">PE</th>
                        <th style="width:5%;">PP</th>
                        <th style="width:5%;">GF</th>
                        <th style="width:5%;">GC</th>
                        <th style="width:5%;">DG</th>
                        <th style="width:6%;">W.O.</th>
                        <th style="width:7%;">PTS</th>
                        <th style="width:7%;">OBS</th>
                        <th style="width:10%;">PTS FINAL</th>
                    </tr>
                </thead>
                <tbody>
        ';

        if ($standings->isEmpty()) {
            return $html.'
                    <tr>
                        <td colspan="13" style="text-align:center;color:#64748b;">No hay equipos inscritos para esta categoria y serie.</td>
                    </tr>
                </tbody>
            </table>
            <div style="height:5px;"></div>
            ';
        }

        foreach ($standings as $row) {
            $background = $row['position'] <= 3 ? '#eef6ff' : '#ffffff';
            $obsColor = $row['adjustment_points'] < 0 ? '#dc2626' : ($row['adjustment_points'] > 0 ? '#15803d' : '#64748b');
            $goalDifference = ($row['goal_difference'] > 0 ? '+' : '').$row['goal_difference'];
            $obs = ($row['adjustment_points'] > 0 ? '+' : '').$row['adjustment_points'];

            $html .= '
                    <tr style="background-color:'.$background.';font-size:7px;">
                        <td style="width:5%;text-align:center;font-weight:bold;">'.e((string) $row['position']).'</td>
                        <td style="width:25%;font-weight:bold;">'.e($row['team_name']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['played']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['won']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['drawn']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['lost']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['goals_for']).'</td>
                        <td style="width:5%;text-align:center;">'.e((string) $row['goals_against']).'</td>
                        <td style="width:5%;text-align:center;font-weight:bold;">'.e($goalDifference).'</td>
                        <td style="width:6%;text-align:center;color:#dc2626;font-weight:bold;">'.e((string) $row['walkovers']).'</td>
                        <td style="width:7%;text-align:center;font-weight:bold;">'.e((string) $row['points']).'</td>
                        <td style="width:7%;text-align:center;color:'.$obsColor.';font-weight:bold;">'.e($obs).'</td>
                        <td style="width:10%;text-align:center;color:#1d4ed8;font-weight:bold;">'.e((string) $row['final_points']).'</td>
                    </tr>
            ';
        }

        return $html.'
                </tbody>
            </table>
            <div style="height:5px;"></div>
        ';
    }

    private function adjustmentsHtml(Collection $adjustments): string
    {
        $html = '
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#f1f5f9;color:#132f4c;">
                <tr>
                    <td style="width:100%;text-align:center;font-size:8px;font-weight:bold;">RESOLUCIONES ADMINISTRATIVAS</td>
                </tr>
            </table>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead>
                    <tr style="background-color:#e2e8f0;color:#132f4c;font-weight:bold;text-align:center;font-size:7px;">
                        <th style="width:25%;text-align:left;">EQUIPO</th>
                        <th style="width:10%;">PUNTOS</th>
                        <th style="width:50%;text-align:left;">MOTIVO</th>
                        <th style="width:15%;">FECHA</th>
                    </tr>
                </thead>
                <tbody>
        ';

        if ($adjustments->isEmpty()) {
            return $html.'
                    <tr>
                        <td colspan="4" style="text-align:center;color:#64748b;">No hay resoluciones administrativas registradas.</td>
                    </tr>
                </tbody>
            </table>
            <div style="height:5px;"></div>
            ';
        }

        foreach ($adjustments as $adjustment) {
            /** @var StandingAdjustment $adjustment */
            $points = ($adjustment->points_adjustment > 0 ? '+' : '').$adjustment->points_adjustment;
            $color = $adjustment->points_adjustment < 0 ? '#dc2626' : '#15803d';

            $html .= '
                    <tr style="font-size:7px;">
                        <td style="width:25%;font-weight:bold;">'.e($adjustment->team?->name ?? '-').'</td>
                        <td style="width:10%;text-align:center;color:'.$color.';font-weight:bold;">'.e($points).'</td>
                        <td style="width:50%;">'.e($adjustment->reason).'</td>
                        <td style="width:15%;text-align:center;">'.e($adjustment->created_at?->format('d/m/Y H:i') ?? '-').'</td>
                    </tr>
            ';
        }

        return $html.'
                </tbody>
            </table>
            <div style="height:5px;"></div>
        ';
    }

    private function topScorersHtml(Collection $topScorers): string
    {
        $html = '
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#f1f5f9;color:#132f4c;">
                <tr>
                    <td style="width:100%;text-align:center;font-size:8px;font-weight:bold;">GOLEADORES</td>
                </tr>
            </table>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead>
                    <tr style="background-color:#e2e8f0;color:#132f4c;font-weight:bold;text-align:center;font-size:7px;">
                        <th style="width:8%;">POS</th>
                        <th style="width:42%;text-align:left;">JUGADOR</th>
                        <th style="width:35%;text-align:left;">EQUIPO</th>
                        <th style="width:15%;">GOLES</th>
                    </tr>
                </thead>
                <tbody>
        ';

        if ($topScorers->isEmpty()) {
            return $html.'
                    <tr>
                        <td colspan="4" style="text-align:center;color:#64748b;">Aun no hay goles registrados.</td>
                    </tr>
                </tbody>
            </table>
            ';
        }

        foreach ($topScorers as $index => $scorer) {
            $html .= '
                    <tr style="font-size:7px;">
                        <td style="width:8%;text-align:center;font-weight:bold;">'.e((string) ($index + 1)).'</td>
                        <td style="width:42%;font-weight:bold;">'.e($scorer['player_name']).'</td>
                        <td style="width:35%;">'.e($scorer['team_name']).'</td>
                        <td style="width:15%;text-align:center;color:#15803d;font-weight:bold;">'.e((string) $scorer['goals']).'</td>
                    </tr>
            ';
        }

        return $html.'
                </tbody>
            </table>
        ';
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

    private function reportFooterHtml(?Company $company): string
    {
        $footer = $company?->report_footer;

        if (! $footer) {
            return '<div style="height:4px;"></div>';
        }

        return '
            <div style="height:4px;"></div>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:#132f4c;color:#ffffff;">
                <tr>
                    <td style="width:100%;font-size:7px;text-align:center;">'.e($footer).'</td>
                </tr>
            </table>
        ';
    }

    private function filename(Tournament $tournament, array $group): string
    {
        $name = Str::slug('tabla posiciones '.$tournament->name.' '.$group['label']);

        return ($name ?: 'tabla-posiciones').'.pdf';
    }
}
