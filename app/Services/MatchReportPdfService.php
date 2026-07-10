<?php

namespace App\Services;

use App\Models\Company;
use App\Models\FixtureMatch;
use App\Models\MatchReport;
use App\Models\MatchReportPlayer;
use App\Models\MatchdayDateFiscal;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class MatchReportPdfService
{
    public function controlSheet(MatchReport $report): Response
    {
        $report->load([
            'company',
            'fixtureMatch.homeTeam',
            'fixtureMatch.awayTeam',
            'fixtureMatch.category',
            'fixtureMatch.tournament',
            'fixtureMatch.matchdayDate.court',
            'fixtureMatch.matchdayDate.fiscals.team',
            'fixtureMatch.matchdayDate.matchday.season',
            'players.player',
            'players.team',
        ]);

        $pdf = $this->makePdf($this->title($report));
        $pdf->AddPage();
        $pdf->writeHTML($this->html($report), true, false, true, false, '');

        return response($pdf->Output($this->filename($report), 'S'), 200, [
            'Content-Disposition' => 'inline; filename="'.$this->filename($report).'"',
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function makePdf(string $title): TCPDF
    {
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name', 'Nexgol'));
        $pdf->SetAuthor(config('app.name', 'Nexgol'));
        $pdf->SetTitle($title);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 8);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 8);

        return $pdf;
    }

    private function html(MatchReport $report): string
    {
        $match = $report->fixtureMatch;
        $homeName = $this->teamLabel($match->homeTeam?->name, $match->home_seed);
        $awayName = $this->teamLabel($match->awayTeam?->name, $match->away_seed);
        $homePlayers = $report->players->where('team_side', 'home')->values();
        $awayPlayers = $report->players->where('team_side', 'away')->values();

        return '
            '.$this->headerHtml($report).'
            '.$this->detailHtml($report).'
            <div style="height:6px;"></div>
            <table cellpadding="2" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:45%;text-align:center;font-size:15px;">Club: <strong>'.e($homeName).'</strong></td>
                    <td style="width:10%;text-align:center;font-size:17px;color:#1d4ed8;font-weight:bold;">'.e((string) $report->home_score).' &nbsp;&nbsp; '.e((string) $report->away_score).'</td>
                    <td style="width:45%;text-align:center;font-size:15px;">Club: <strong>'.e($awayName).'</strong></td>
                </tr>
            </table>
            <table cellpadding="0" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:49%;">'.$this->playersTableHtml($homePlayers, $report, 'home', $homeName).'</td>
                    <td style="width:2%;"></td>
                    <td style="width:49%;">'.$this->playersTableHtml($awayPlayers, $report, 'away', $awayName).'</td>
                </tr>
            </table>
            <div style="height:6px;"></div>
            '.$this->resultHtml($report, $homeName, $awayName).'
            '.$this->observationsHtml($report).'
        ';
    }

    private function headerHtml(MatchReport $report): string
    {
        $company = $report->company;

        return '
            <table cellpadding="0" cellspacing="0" style="width:100%;">
                <tr>
                    <td style="width:18%;text-align:left;">'.$this->logoHtml($company).'</td>
                    <td style="width:34%;text-align:center;padding-top:6px;">
                        <div style="font-size:10px;font-weight:bold;color:#111827;">LIGA DEPORTIVA</div>
                        <div style="font-size:16px;font-weight:bold;color:#111827;">'.e(str($company?->name ?? config('app.name', 'Nexgol'))->upper()->toString()).'</div>
                        <div style="font-size:8px;color:#dc2626;font-weight:bold;">'.e($this->foundationLabel($company)).'</div>
                        <div style="font-size:8px;color:#111827;font-weight:bold;">'.e($this->legalPersonalityLabel($company)).'</div>
                    </td>
                    <td style="width:48%;">'.$this->controlBoxHtml($report).'</td>
                </tr>
            </table>
            <div style="height:8px;"></div>
        ';
    }

    private function controlBoxHtml(MatchReport $report): string
    {
        $fiscal = $this->fiscalForMatch($report->fixtureMatch)?->team?->name ?? '';

        return '
            <table cellpadding="2" cellspacing="0" border="1" style="width:100%;border-color:#cbd5e1;">
                <tr style="background-color:#f3f4f6;">
                    <td colspan="2" style="text-align:center;font-size:9px;font-weight:bold;">PLANILLA DE CONTROL DE MESA</td>
                </tr>
                '.$this->controlRow('Fiscal del turno:', $fiscal).'
                '.$this->controlRow('Arbitro 1:', $report->referee_1).'
                '.$this->controlRow('Arbitro 2:', $report->referee_2).'
                '.$this->controlRow('Arbitro 3:', $report->referee_3).'
                '.$this->controlRow('Estado:', $report->status === 'walkover' ? 'W.O.' : 'FINALIZADO').'
            </table>
        ';
    }

    private function controlRow(string $label, ?string $value): string
    {
        return '
            <tr>
                <td style="width:42%;font-size:8px;">'.e($label).'</td>
                <td style="width:58%;font-size:8px;font-weight:bold;">'.e($value ?: '-').'</td>
            </tr>
        ';
    }

    private function detailHtml(MatchReport $report): string
    {
        $match = $report->fixtureMatch;
        $date = $match->matchdayDate;

        return '
            <table cellpadding="2" cellspacing="0" border="1" style="width:82%;margin-left:9%;border-color:#9ca3af;">
                <tr>
                    <td>
                        <table cellpadding="1" cellspacing="0" border="0" style="width:100%;">
                            <tr style="background-color:#f3f4f6;">
                                <td colspan="4" style="text-align:center;font-size:9px;font-weight:bold;">DETALLE DEL PARTIDO</td>
                            </tr>
                            <tr>
                                <td style="width:17%;font-size:7px;font-weight:bold;">HORA:</td>
                                <td style="width:33%;font-size:7px;font-weight:bold;">'.e($this->timeLabel($match->scheduled_time)).'</td>
                                <td style="width:17%;font-size:7px;font-weight:bold;">TORNEO:</td>
                                <td style="width:33%;font-size:7px;font-weight:bold;">'.e($match->tournament?->name ?? '-').'</td>
                            </tr>
                            <tr>
                                <td style="font-size:7px;font-weight:bold;">FECHA PARTIDO:</td>
                                <td style="font-size:7px;font-weight:bold;">'.e($date?->date?->copy()->locale('es')->translatedFormat('l d \d\e F \d\e Y') ?? '-').'</td>
                                <td style="font-size:7px;font-weight:bold;">CATEGORIA:</td>
                                <td style="font-size:7px;font-weight:bold;">'.e($match->category?->name ?? '-').'</td>
                            </tr>
                            <tr>
                                <td style="font-size:7px;font-weight:bold;">CANCHA:</td>
                                <td style="font-size:7px;font-weight:bold;">'.e($date?->court?->name ?? '-').'</td>
                                <td style="font-size:7px;font-weight:bold;">SERIE:</td>
                                <td style="font-size:7px;font-weight:bold;">'.e($match->series ?? '-').'</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        ';
    }

    private function playersTableHtml(Collection $players, MatchReport $report, string $side, string $teamName): string
    {
        $rows = $players->map(fn (MatchReportPlayer $player, int $index): string => '
            <tr>
                <td style="width:10%;font-size:6px;text-align:center;">'.e((string) ($player->jersey_number ?? '-')).'</td>
                <td style="width:54%;font-size:6px;">'.e($player->player?->full_name ?? '-').'</td>
                <td style="width:9%;font-size:6px;text-align:center;">'.e((string) $player->goals).'</td>
                <td style="width:9%;font-size:6px;text-align:center;">'.e((string) $player->yellow_cards).'</td>
                <td style="width:9%;font-size:6px;text-align:center;">0</td>
                <td style="width:9%;font-size:6px;text-align:center;">'.e((string) $player->red_cards).'</td>
            </tr>
        ')->implode('');

        if ($this->isWalkoverSide($report, $side)) {
            $rows = '
                <tr>
                    <td colspan="6" style="height:24mm;font-size:13px;text-align:center;color:#dc2626;font-weight:bold;">
                        '.e($this->walkoverTeamMessage($report, $teamName)).'
                    </td>
                </tr>
            ';
        } elseif ($rows === '') {
            $rows = '
                <tr>
                    <td colspan="6" style="font-size:7px;text-align:center;color:#6b7280;">Sin jugadores registrados.</td>
                </tr>
            ';
        }

        return '
            <table cellpadding="1" cellspacing="0" border="1" style="width:100%;border-color:#9ca3af;">
                <thead>
                    <tr style="background-color:#f3f4f6;font-weight:bold;text-align:center;">
                        <th style="width:10%;font-size:6px;">Nro.</th>
                        <th style="width:54%;font-size:6px;">Jugador</th>
                        <th style="width:9%;font-size:6px;">Goles</th>
                        <th style="width:9%;font-size:6px;">T.AM</th>
                        <th style="width:9%;font-size:6px;">T.DA</th>
                        <th style="width:9%;font-size:6px;">T.RD</th>
                    </tr>
                </thead>
                <tbody>'.$rows.'</tbody>
            </table>
        ';
    }

    private function isWalkoverSide(MatchReport $report, string $side): bool
    {
        return $report->status === 'walkover'
            && ($report->wo_side === $side || $report->wo_side === 'double');
    }

    private function walkoverTeamMessage(MatchReport $report, string $teamName): string
    {
        $reason = $report->wo_reason === 'court_fee'
            ? 'NO PAGO DERECHO DE CANCHA'
            : 'NO SE PRESENTO';

        return str($teamName)->upper()->toString().' '.$reason.' (W.O.)';
    }

    private function resultHtml(MatchReport $report, string $homeName, string $awayName): string
    {
        $homeYellow = $report->players->where('team_side', 'home')->sum('yellow_cards');
        $awayYellow = $report->players->where('team_side', 'away')->sum('yellow_cards');
        $homeRed = $report->players->where('team_side', 'home')->sum('red_cards');
        $awayRed = $report->players->where('team_side', 'away')->sum('red_cards');
        $winner = $this->winnerLabel($report, $homeName, $awayName);

        return '
            <table cellpadding="2" cellspacing="0" style="width:100%;">
                <tr style="background-color:#f3f4f6;">
                    <td colspan="5" style="text-align:center;font-size:9px;font-weight:bold;">RESULTADO FINAL</td>
                </tr>
                <tr>
                    <td style="width:30%;text-align:center;font-size:7px;">'.e($homeName).'</td>
                    <td style="width:15%;background-color:#f3f4f6;text-align:center;font-size:11px;font-weight:bold;">'.e((string) $report->home_score).'</td>
                    <td style="width:10%;text-align:center;font-size:8px;">X</td>
                    <td style="width:15%;background-color:#f3f4f6;text-align:center;font-size:11px;font-weight:bold;">'.e((string) $report->away_score).'</td>
                    <td style="width:30%;text-align:center;font-size:7px;">'.e($awayName).'</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-size:7px;">AMARILLAS</td>
                    <td style="background-color:#f3f4f6;text-align:center;font-size:8px;font-weight:bold;">'.e((string) $homeYellow).'</td>
                    <td></td>
                    <td style="background-color:#f3f4f6;text-align:center;font-size:8px;font-weight:bold;">'.e((string) $awayYellow).'</td>
                    <td style="text-align:center;font-size:7px;">AMARILLAS</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-size:7px;">ROJAS</td>
                    <td style="background-color:#f3f4f6;text-align:center;font-size:8px;font-weight:bold;">'.e((string) $homeRed).'</td>
                    <td></td>
                    <td style="background-color:#f3f4f6;text-align:center;font-size:8px;font-weight:bold;">'.e((string) $awayRed).'</td>
                    <td style="text-align:center;font-size:7px;">ROJAS</td>
                </tr>
                <tr style="background-color:#f9fafb;">
                    <td style="width:30%;text-align:center;font-size:9px;font-weight:bold;">GANADOR</td>
                    <td colspan="4" style="width:70%;text-align:center;font-size:9px;font-weight:bold;">'.e($winner).'</td>
                </tr>
            </table>
        ';
    }

    private function observationsHtml(MatchReport $report): string
    {
        $wo = $this->walkoverLabel($report);
        $notes = trim((string) $report->notes);

        if ($wo === '' && $notes === '') {
            return '';
        }

        return '
            <div style="height:4px;"></div>
            <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#cbd5e1;">
                <tr style="background-color:#f3f4f6;">
                    <td style="font-size:8px;font-weight:bold;">OBSERVACIONES</td>
                </tr>
                <tr>
                    <td style="font-size:8px;">'.e(trim($wo.' '.$notes)).'</td>
                </tr>
            </table>
        ';
    }

    private function fiscalForMatch(FixtureMatch $match): ?MatchdayDateFiscal
    {
        if (! $match->scheduled_time || ! $match->matchdayDate) {
            return null;
        }

        $time = Carbon::parse($match->scheduled_time)->format('H:i:s');

        return $match->matchdayDate->fiscals
            ->first(fn (MatchdayDateFiscal $fiscal): bool => $fiscal->start_time <= $time && $fiscal->end_time > $time);
    }

    private function winnerLabel(MatchReport $report, string $homeName, string $awayName): string
    {
        if ($report->status === 'walkover') {
            return match ($report->wo_side) {
                'home' => $awayName,
                'away' => $homeName,
                'double' => 'DOBLE W.O.',
                default => 'W.O.',
            };
        }

        if ($report->home_score === $report->away_score) {
            return 'EMPATE';
        }

        return $report->home_score > $report->away_score ? $homeName : $awayName;
    }

    private function walkoverLabel(MatchReport $report): string
    {
        if ($report->status !== 'walkover') {
            return '';
        }

        $reason = $report->wo_reason === 'court_fee'
            ? 'derecho de cancha'
            : 'inasistencia';

        return match ($report->wo_side) {
            'home' => 'W.O. del equipo local por '.$reason.'.',
            'away' => 'W.O. del equipo visitante por '.$reason.'.',
            'double' => 'Doble W.O. por '.$reason.'.',
            default => 'Partido definido por W.O.',
        };
    }

    private function logoHtml(?Company $company): string
    {
        if ($company?->logo_display_url) {
            return '<img src="'.$company->logo_display_url.'" style="height:28mm;max-width:32mm;">';
        }

        return '
            <table cellpadding="4" cellspacing="0" style="width:31mm;height:24mm;border:1px solid #cbd5e1;background-color:#f8fafc;">
                <tr>
                    <td style="text-align:center;color:#64748b;font-size:8px;">LOGO</td>
                </tr>
            </table>
        ';
    }

    private function foundationLabel(?Company $company): string
    {
        return $company?->foundation_date
            ? 'Fundado el '.$company->foundation_date->copy()->locale('es')->translatedFormat('d \d\e F \d\e Y')
            : '';
    }

    private function legalPersonalityLabel(?Company $company): string
    {
        return $company?->legal_personality
            ? 'PERSONERIA JURIDICA '.$company->legal_personality
            : '';
    }

    private function timeLabel(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '--:--';
    }

    private function teamLabel(?string $teamName, ?string $seed): string
    {
        return $teamName ?: ($seed ?: 'Por definir');
    }

    private function title(MatchReport $report): string
    {
        return 'Planilla de partido '.$report->id;
    }

    private function filename(MatchReport $report): string
    {
        $match = $report->fixtureMatch;
        $home = $this->teamLabel($match->homeTeam?->name, $match->home_seed);
        $away = $this->teamLabel($match->awayTeam?->name, $match->away_seed);

        return Str::slug('planilla '.$home.' '.$away.' '.$report->id).'.pdf';
    }
}
