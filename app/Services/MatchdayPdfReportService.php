<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use App\Models\MatchdayDateFiscal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class MatchdayPdfReportService
{
    public function fixture(array $context): Response
    {
        /** @var Matchday $matchday */
        $matchday = $context['matchday'];
        /** @var Collection $dates */
        $dates = $context['dates'];

        $pdf = $this->makePdf('Rol de partidos '.$this->matchdayTitle($matchday));

        if ($dates->isEmpty()) {
            $pdf->AddPage();
            $this->writePageHeader($pdf, $context);
            $pdf->writeHTML($this->emptyFixtureHtml().$this->reportFooterHtml($context), true, false, true, false, '');
        }

        foreach ($dates as $index => $date) {
            if ($index === 0 || ! $this->dateFitsCurrentPage($pdf, $date)) {
                $pdf->AddPage();
                $this->writePageHeader($pdf, $context);
            }

            $pdf->writeHTML($this->dateBlockHtml($date, $index), true, false, true, false, '');
        }

        return $this->pdfResponse($pdf, $this->filename($matchday));
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

    private function writePageHeader(TCPDF $pdf, array $context): void
    {
        $company = $context['season']->company;
        $startY = $pdf->GetY();

        $pdf->writeHTML($this->fixtureTitleHtml($context), true, false, true, false, '');

        $this->drawLogo($pdf, $company, $startY);
    }

    private function dateFitsCurrentPage(TCPDF $pdf, MatchdayDate $date): bool
    {
        $bottomLimit = $pdf->getPageHeight() - $pdf->getBreakMargin();

        return ($pdf->GetY() + $this->estimatedDateBlockHeight($date)) <= $bottomLimit;
    }

    private function estimatedDateBlockHeight(MatchdayDate $date): float
    {
        return 17 + ($date->fixtureMatches->count() * 7);
    }

    private function fixtureTitleHtml(array $context): string
    {
        /** @var Matchday $matchday */
        $matchday = $context['matchday'];
        $season = $context['season'];
        $company = $season->company;

        return $this->reportHeaderHtml($company, '
            <div style="font-size:8px;color:#64748b;font-weight:bold;">JORNADA</div>
            <div style="font-size:34px;line-height:34px;color:#132f4c;font-weight:bold;">'.e((string) $matchday->number).'</div>
        ').'
            <table cellpadding="5" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:100%;text-align:center;color:#132f4c;font-size:12px;font-weight:bold;">
                        FIXTURE CORRESPONDIENTE A LA '.e($this->ordinalFeminine((int) $matchday->number)).' JORNADA GESTION '.e(str($season->name)->upper()->toString()).'
                    </td>
                </tr>
            </table>
            <div style="height:3px;"></div>
        ';
    }

    private function reportFooterHtml(array $context): string
    {
        $season = $context['season'];
        $companyName = $season->company?->name ?? config('app.name', 'Nexgol');

        return '
            <table cellpadding="5" cellspacing="0" style="width:100%;background-color:#132f4c;color:#ffffff;">
                <tr>
                    <td style="width:70%;font-size:8px;">'.e($companyName).'</td>
                    <td style="width:30%;font-size:8px;text-align:right;">'.e(config('app.name', 'Nexgol')).'</td>
                </tr>
            </table>
        ';
    }

    private function emptyFixtureHtml(): string
    {
        return '
            <table cellpadding="8" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <tr>
                    <td style="text-align:center;color:#64748b;">No hay fechas configuradas para esta jornada.</td>
                </tr>
            </table>
            <div style="height:5px;"></div>
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
        if ($company?->logo_local_path) {
            return '';
        }

        return '
            <table cellpadding="4" cellspacing="0" style="width:31mm;height:22mm;border:1px solid #cbd5e1;background-color:#f8fafc;">
                <tr>
                    <td style="text-align:center;color:#64748b;font-size:8px;">LOGO</td>
                </tr>
            </table>
        ';
    }

    private function drawLogo(TCPDF $pdf, ?Company $company, float $headerY): void
    {
        if (! $company?->logo_local_path) {
            return;
        }

        $pdf->Image(
            $company->logo_local_path,
            10,
            $headerY + 4,
            30,
            22,
            '',
            '',
            '',
            true,
            300,
            '',
            false,
            false,
            0,
            false,
            false,
            false
        );
    }

    private function dateBlockHtml(MatchdayDate $date, int $index): string
    {
        $dateLabel = str($date->date->copy()->locale('es')->translatedFormat('l j \d\e F Y'))->upper()->toString();
        $accent = $index % 2 === 0 ? '#0f766e' : '#1d4ed8';

        $html = '
            <table cellpadding="3" cellspacing="0" style="width:100%;background-color:#132f4c;color:#ffffff;">
                <tr>
                    <td style="width:100%;text-align:center;font-size:9px;font-weight:bold;">CANCHA: '.e(str($date->court?->name ?? 'Sin cancha')->upper()->toString()).'</td>
                </tr>
            </table>
            <table cellpadding="4" cellspacing="0" style="width:100%;background-color:'.$accent.';color:#ffffff;">
                <tr>
                    <td style="width:100%;text-align:center;font-size:12px;font-weight:bold;">'.e($dateLabel).'</td>
                </tr>
            </table>
            <table cellpadding="2" cellspacing="0" border="1" style="width:100%;border-color:#d5dde8;">
                <thead>
                    <tr style="background-color:#132f4c;color:#ffffff;font-weight:bold;text-align:center;font-size:7px;">
                        <th style="width:12%;">HORARIO</th>
                        <th style="width:9%;">CAT.</th>
                        <th style="width:28%;">EQUIPO A</th>
                        <th style="width:4%;">VS</th>
                        <th style="width:28%;">EQUIPO B</th>
                        <th style="width:19%;">FISCAL</th>
                    </tr>
                </thead>
                <tbody>
        ';

        foreach ($date->fixtureMatches as $row => $match) {
            $html .= $this->matchRowHtml($date, $match, $row);
        }

        return $html.'
                </tbody>
            </table>
            <div style="height:5px;"></div>
        ';
    }

    private function matchRowHtml(MatchdayDate $date, FixtureMatch $match, int $row): string
    {
        $color = $this->categoryColor($match);
        $category = $match->category?->name ?? '-';
        $round = $this->roundLabel($match);
        $home = $this->teamLabel($match->homeTeam?->name, $match->home_seed);
        $away = $this->teamLabel($match->awayTeam?->name, $match->away_seed);
        $fiscal = $this->fiscalForMatch($date, $match);

        return '
            <tr style="background-color:'.$color.';">
                <td style="width:12%;text-align:center;background-color:'.$color.';color:#132f4c;">
                    <span style="font-size:10px;font-weight:bold;">'.e($this->timeLabel($match->scheduled_time)).'</span>
                </td>
                <td style="width:9%;background-color:'.$color.';color:#132f4c;text-align:center;font-weight:bold;font-size:7px;">
                    '.e($category).'<br><span style="font-size:5px;color:#475569;">'.e($round).'</span>
                </td>
                <td style="width:28%;background-color:'.$color.';text-align:right;color:#132f4c;font-size:10px;font-weight:bold;">
                    '.e($home).'
                </td>
                <td style="width:4%;background-color:'.$color.';text-align:center;color:#e63946;font-size:8px;font-weight:bold;">
                    VS
                </td>
                <td style="width:28%;background-color:'.$color.';text-align:left;color:#132f4c;font-size:10px;font-weight:bold;">
                    '.e($away).'
                </td>
                <td style="width:19%;background-color:'.$color.';text-align:center;color:#132f4c;font-size:7px;font-weight:bold;">
                    '.e($fiscal?->team?->name ?? '-').'
                </td>
            </tr>
        ';
    }

    private function fiscalForMatch(MatchdayDate $date, FixtureMatch $match): ?MatchdayDateFiscal
    {
        if (! $match->scheduled_time) {
            return null;
        }

        $time = Carbon::parse($match->scheduled_time)->format('H:i:s');

        return $date->fiscals->first(fn (MatchdayDateFiscal $fiscal): bool => $fiscal->start_time <= $time && $fiscal->end_time > $time);
    }

    private function categoryColor(FixtureMatch $match): string
    {
        $palette = [
            '#fde68a',
            '#bfdbfe',
            '#bbf7d0',
            '#fecaca',
            '#ddd6fe',
            '#fed7aa',
            '#bae6fd',
            '#fbcfe8',
        ];

        $key = (string) ($match->category_id ?: $match->category?->name ?: 'sin-categoria');

        return $palette[abs(crc32($key)) % count($palette)];
    }

    private function teamLabel(?string $teamName, ?string $seed): string
    {
        return $teamName ?: ($seed ?: 'Por definir');
    }

    private function timeLabel(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '--:--';
    }

    private function roundLabel(FixtureMatch $match): string
    {
        $label = $match->round_number
            ? 'Fecha '.$match->round_number
            : ($match->tie_number ? $match->stage.' - Llave '.$match->tie_number : $match->stage);

        return $label.($match->leg_number > 1 ? ' - Vuelta' : '');
    }

    private function matchdayTitle(Matchday $matchday): string
    {
        return $matchday->name ?? 'Jornada '.$matchday->number;
    }

    private function ordinalFeminine(int $number): string
    {
        $ordinals = [
            1 => 'PRIMERA',
            2 => 'SEGUNDA',
            3 => 'TERCERA',
            4 => 'CUARTA',
            5 => 'QUINTA',
            6 => 'SEXTA',
            7 => 'SEPTIMA',
            8 => 'OCTAVA',
            9 => 'NOVENA',
            10 => 'DECIMA',
            11 => 'DECIMO PRIMERA',
            12 => 'DECIMO SEGUNDA',
            13 => 'DECIMO TERCERA',
            14 => 'DECIMO CUARTA',
            15 => 'DECIMO QUINTA',
            16 => 'DECIMO SEXTA',
            17 => 'DECIMO SEPTIMA',
            18 => 'DECIMO OCTAVA',
            19 => 'DECIMO NOVENA',
            20 => 'VIGESIMA',
        ];

        return $ordinals[$number] ?? (string) $number;
    }

    private function filename(Matchday $matchday): string
    {
        return Str::slug($this->matchdayTitle($matchday) ?: 'jornada').'-rol-partidos.pdf';
    }
}
