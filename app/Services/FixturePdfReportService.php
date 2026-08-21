<?php

namespace App\Services;

use App\Models\FixtureGeneration;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class FixturePdfReportService
{
    public function fixture(array $context): Response
    {
        /** @var FixtureGeneration $generation */
        $generation = $context['generation'];
        $matchesByStage = $context['matchesByStage'];

        $pdf = $this->makePdf('Fixture '.$generation->tournament?->name);
        $pdf->AddPage();
        $pdf->writeHTML($this->printableFixtureHtml($generation, $matchesByStage), true, false, true, false, '');

        return $this->pdfResponse($pdf, 'fixture-'.$generation->id.'.pdf');
    }

    public function fixtureByTeam(array $context): Response
    {
        /** @var FixtureGeneration $generation */
        $generation = $context['generation'];
        $matchesByStage = $context['matchesByStage'];

        $pdf = $this->makePdf('Fixture por equipo '.$generation->tournament?->name);
        $groupMatches = $matchesByStage
            ->filter(fn (Collection $matches, string $stageKey): bool => str_starts_with($stageKey, 'group|'))
            ->flatten(1)
            ->sortBy([['series', 'asc'], ['round_number', 'asc'], ['match_number', 'asc']])
            ->values();

        $teams = $this->teamsFromMatches($groupMatches);

        if ($teams->isEmpty()) {
            $pdf->AddPage();
            $pdf->writeHTML('<h1>No existen partidos de primera fase para imprimir por equipo.</h1>', true, false, true, false, '');

            return $this->pdfResponse($pdf, 'fixture-por-equipo-'.$generation->id.'.pdf');
        }

        foreach ($teams->chunk(4) as $teamPage) {
            $pdf->AddPage();
            $html = '';

            foreach ($teamPage as $team) {
                $teamMatches = $groupMatches
                    ->filter(fn ($match): bool => (int) $match->home_team_id === (int) $team['id'] || (int) $match->away_team_id === (int) $team['id'])
                    ->values();

                $html .= $this->teamFixtureBlockHtml($generation, $team, $teamMatches);
            }

            $pdf->writeHTML($html, true, false, true, false, '');
        }

        return $this->pdfResponse($pdf, 'fixture-por-equipo-'.$generation->id.'.pdf');
    }

    public function patterns(Collection $patterns): Response
    {
        $pdf = $this->makePdf('Plantillas de fixture');
        $pdf->AddPage();
        $pdf->writeHTML($this->patternsHtml($patterns), true, false, true, false, '');

        return $this->pdfResponse($pdf, 'plantillas-fixture.pdf');
    }

    private function makePdf(string $title): TCPDF
    {
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name', 'Nexgol'));
        $pdf->SetAuthor(config('app.name', 'Nexgol'));
        $pdf->SetTitle($title);
        $pdf->SetMargins(6, 7, 6);
        $pdf->SetAutoPageBreak(true, 7);
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

    private function fixtureHtml(FixtureGeneration $generation, Collection $matchesByStage): string
    {
        $firstPhase = ((int) ($generation->config['first_phase_rounds'] ?? 1)) === 2 ? 'Ida y vuelta' : 'Solo ida';
        $secondPhase = [
            'knockout' => 'Llaves',
            'league' => 'Liguilla',
            'accumulative' => 'Acumulativo',
        ][$generation->config['second_phase_mode'] ?? ''] ?? 'Por definir';

        $html = '
            <h1 style="font-size:18px;color:#1d4f91;margin:0;">Fixture generado</h1>
            <table cellpadding="4" cellspacing="0" style="width:100%;border-bottom:2px solid #1d4f91;">
                <tr>
                    <td style="width:65%;">
                        <strong style="font-size:14px;">'.e($generation->tournament?->name ?? '-').'</strong><br>
                        Categoria: '.e($generation->category?->name ?? '-').' | Division: '.e($generation->tournament?->division?->name ?? '-').'
                    </td>
                    <td style="width:35%;text-align:right;">
                        '.e($generation->company?->name ?? config('app.name')).'<br>
                        Generado: '.e($generation->generated_at?->format('d/m/Y H:i') ?? '-').'<br>
                        Partidos: '.e((string) $generation->matches_count).'
                    </td>
                </tr>
            </table>
            <br>
            <table cellpadding="5" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#f4f7fb;">
                    <td><strong>Primera fase</strong><br>'.e($firstPhase).'</td>
                    <td><strong>Clasificacion</strong><br>'.e((string) ($generation->config['qualifiers_per_series'] ?? 0)).' por serie</td>
                    <td><strong>Segunda fase</strong><br>'.e($secondPhase).'</td>
                    <td><strong>Estado</strong><br>'.e(ucfirst($generation->status)).'</td>
                </tr>
            </table>
            <br>
        ';

        foreach ($matchesByStage as $stageKey => $matches) {
            [$phase, $stage] = explode('|', $stageKey, 2);
            $phaseLabel = [
                'group' => 'Primera fase',
                'knockout' => 'Llaves',
                'league' => 'Liguilla',
                'league_playoff' => 'Definicion de liguilla',
            ][$phase] ?? $phase;

            $html .= '
                <h2 style="font-size:13px;color:#1d4f91;margin-bottom:4px;">'.e($stage).'</h2>
                <div style="font-size:8px;color:#667085;margin-bottom:4px;">'.e($phaseLabel).' | '.e((string) $matches->count()).' partidos</div>
                <table cellpadding="4" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                    <thead>
                        <tr style="background-color:#eef3f9;font-weight:bold;">
                            <th style="width:7%;">#</th>
                            <th style="width:16%;">Ronda</th>
                            <th style="width:13%;">Serie</th>
                            <th style="width:27%;">Local / ref.</th>
                            <th style="width:27%;">Visitante / ref.</th>
                            <th style="width:10%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            foreach ($matches as $match) {
                $round = $this->roundLabel($match);
                $series = $match->series ? (TournamentRegistration::SERIES[$match->series] ?? $match->series) : '-';

                $html .= '
                    <tr>
                        <td style="width:7%;">'.e((string) $match->match_number).'</td>
                        <td style="width:16%;">'.e($round).'</td>
                        <td style="width:13%;">'.e($series).'</td>
                        <td style="width:27%;">'.e($match->homeTeam?->name ?? $match->home_seed ?? 'Por definir').'</td>
                        <td style="width:27%;">'.e($match->awayTeam?->name ?? $match->away_seed ?? 'Por definir').'</td>
                        <td style="width:10%;">Pendiente</td>
                    </tr>
                ';
            }

            $html .= '
                    </tbody>
                </table>
                <br>
            ';
        }

        return $html.'
            <p style="font-size:8px;color:#667085;">
                Los partidos se generan independientes de jornadas y quedan disponibles para programacion posterior.
            </p>
        ';
    }

    private function printableFixtureHtml(FixtureGeneration $generation, Collection $matchesByStage): string
    {
        $html = '
            <h1 style="font-size:15px;color:#1d4f91;text-align:center;margin:0;">Planilla Fixture</h1>
            <table cellpadding="3" cellspacing="0" style="width:100%;border-bottom:2px solid #1d4f91;">
                <tr>
                    <td style="width:60%;">
                        <strong>'.e($generation->tournament?->name ?? '-').'</strong><br>
                        Categoria: '.e($generation->category?->name ?? '-').' | Division: '.e($generation->tournament?->division?->name ?? '-').'
                    </td>
                    <td style="width:40%;text-align:right;">
                        '.e($generation->company?->name ?? config('app.name')).'<br>
                        Generado: '.e($generation->generated_at?->format('d/m/Y H:i') ?? '-').'<br>
                        Partidos: '.e((string) $generation->matches_count).'
                    </td>
                </tr>
            </table>
            <br>
        ';

        foreach ($matchesByStage as $stageKey => $matches) {
            [$phase, $stage] = explode('|', $stageKey, 2);
            $phaseLabel = [
                'group' => 'Primera fase',
                'knockout' => 'Llaves',
                'league' => 'Liguilla',
                'league_playoff' => 'Definicion de liguilla',
            ][$phase] ?? $phase;
            $showSeries = $matches->contains(fn ($match): bool => filled($match->series));
            $headerColumns = $showSeries ? 9 : 8;
            $roundWidth = $showSeries ? 11 : 12;
            $homeWidth = $showSeries ? 25 : 28;
            $awayWidth = $showSeries ? 25 : 28;
            $scheduleWidth = $showSeries ? 8 : 10;
            $statusWidth = $showSeries ? 7 : 8;

            $html .= '
                <table cellpadding="3" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                    <thead>
                        <tr style="background-color:#f1f3f5;color:#343a40;">
                            <th colspan="'.$headerColumns.'" style="text-align:center;font-size:10px;">'.e($stage).' - '.e($phaseLabel).'</th>
                        </tr>
                        <tr style="background-color:#f8f9fa;font-weight:bold;text-align:center;">
                            <th style="width:'.$roundWidth.'%;">Ronda</th>
                            '.($showSeries ? '<th style="width:12%;">Serie</th>' : '').'
                            <th style="width:'.$homeWidth.'%;">Local</th>
                            <th style="width:5%;">G</th>
                            <th style="width:4%;">VS</th>
                            <th style="width:5%;">G</th>
                            <th style="width:'.$awayWidth.'%;">Visitante</th>
                            <th style="width:'.$scheduleWidth.'%;">Prog.</th>
                            <th style="width:'.$statusWidth.'%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            foreach ($matches as $match) {
                $round = $this->roundLabel($match);
                $series = $match->series ? (TournamentRegistration::SERIES[$match->series] ?? $match->series) : '-';
                $home = $this->printableTeamName($match->homeTeam?->name, $match->home_seed);
                $away = $this->printableTeamName($match->awayTeam?->name, $match->away_seed);
                $homeScore = $match->report ? (string) $match->report->home_score : '';
                $awayScore = $match->report ? (string) $match->report->away_score : '';

                $html .= '
                    <tr>
                        <td style="width:'.$roundWidth.'%;text-align:center;">'.e($round).'</td>
                        '.($showSeries ? '<td style="width:12%;text-align:center;">'.e($series).'</td>' : '').'
                        <td style="width:'.$homeWidth.'%;text-align:right;">'.$home.'</td>
                        <td style="width:5%;height:15px;text-align:center;font-weight:bold;">'.e($homeScore).'</td>
                        <td style="width:4%;text-align:center;font-weight:bold;background-color:#f8f9fa;">vs</td>
                        <td style="width:5%;text-align:center;font-weight:bold;">'.e($awayScore).'</td>
                        <td style="width:'.$awayWidth.'%;text-align:left;">'.$away.'</td>
                        <td style="width:'.$scheduleWidth.'%;text-align:center;font-size:6px;">'.e($this->scheduleLabel($match)).'</td>
                        <td style="width:'.$statusWidth.'%;text-align:center;font-size:6px;">'.e($this->matchStatusLabel($match)).'</td>
                    </tr>
                ';
            }

            $html .= '
                    </tbody>
                </table>
                <br>
            ';
        }

        return $html;
    }

    private function printableTeamName(?string $teamName, ?string $seed): string
    {
        if ($teamName) {
            return e($teamName);
        }

        if ($seed) {
            return '<span style="color:#667085;">'.e($seed).'</span><br><span style="color:#adb5bd;">________________</span>';
        }

        return '<span style="color:#adb5bd;">________________</span>';
    }

    private function teamsFromMatches(Collection $matches): Collection
    {
        return $matches
            ->flatMap(function ($match): array {
                return [
                    $match->home_team_id ? [
                        'id' => $match->home_team_id,
                        'name' => $match->homeTeam?->name ?? 'Equipo '.$match->home_team_id,
                    ] : null,
                    $match->away_team_id ? [
                        'id' => $match->away_team_id,
                        'name' => $match->awayTeam?->name ?? 'Equipo '.$match->away_team_id,
                    ] : null,
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    private function teamFixtureBlockHtml(FixtureGeneration $generation, array $team, Collection $matches): string
    {
        $html = '
            <table cellpadding="2" cellspacing="0" style="width:100%;border-bottom:1px solid #adb5bd;">
                <tr>
                    <td style="width:65%;">
                        <strong style="font-size:10px;color:#1d4f91;">'.e($team['name']).'</strong><br>
                        <span style="font-size:7px;color:#667085;">
                        '.e($generation->tournament?->name ?? '-').' | '.e($generation->category?->name ?? '-').'
                        </span>
                    </td>
                    <td style="width:35%;text-align:right;font-size:7px;color:#667085;">
                        '.e($generation->company?->name ?? config('app.name')).'<br>
                        Primera fase | '.e((string) $matches->count()).' partidos
                    </td>
                </tr>
            </table>
            <table cellpadding="2" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <thead>
                    <tr style="background-color:#f8f9fa;font-weight:bold;text-align:center;">
                        <th style="width:13%;">Ronda</th>
                        <th style="width:12%;">Serie</th>
                        <th style="width:25%;">Equipo</th>
                        <th style="width:5%;">G</th>
                        <th style="width:4%;">VS</th>
                        <th style="width:5%;">G</th>
                        <th style="width:25%;">Rival</th>
                        <th style="width:7%;">Prog.</th>
                        <th style="width:4%;">Est.</th>
                    </tr>
                </thead>
                <tbody>
        ';

        foreach ($matches as $match) {
            $isHome = (int) $match->home_team_id === (int) $team['id'];
            $opponent = $isHome
                ? ($match->awayTeam?->name ?? 'Por definir')
                : ($match->homeTeam?->name ?? 'Por definir');
            $round = $this->roundLabel($match);
            $series = $match->series ? (TournamentRegistration::SERIES[$match->series] ?? $match->series) : '-';
            $teamScore = '';
            $opponentScore = '';

            if ($match->report) {
                $teamScore = (string) ($isHome ? $match->report->home_score : $match->report->away_score);
                $opponentScore = (string) ($isHome ? $match->report->away_score : $match->report->home_score);
            }

            $html .= '
                <tr>
                    <td style="width:13%;text-align:center;">'.e($round).'</td>
                    <td style="width:12%;text-align:center;">'.e($series).'</td>
                    <td style="width:25%;text-align:right;">'.e($team['name']).'</td>
                    <td style="width:5%;height:11px;text-align:center;font-weight:bold;">'.e($teamScore).'</td>
                    <td style="width:4%;text-align:center;font-weight:bold;background-color:#f8f9fa;">vs</td>
                    <td style="width:5%;text-align:center;font-weight:bold;">'.e($opponentScore).'</td>
                    <td style="width:25%;text-align:left;">'.e($opponent).'</td>
                    <td style="width:7%;text-align:center;font-size:6px;">'.e($this->scheduleLabel($match)).'</td>
                    <td style="width:4%;text-align:center;font-size:6px;">'.e($this->shortStatusLabel($match)).'</td>
                </tr>
            ';
        }

        return $html.'
                </tbody>
            </table>
            <div style="height:8px;"></div>
            <div style="border-top:1px dashed #9aa4b2; margin: 0 10px 6px 10px;"></div>
            <div style="height:10px;"></div>
        ';
    }

    private function matchStatusLabel($match): string
    {
        return match ($match->report?->status) {
            'completed' => 'Finalizado',
            'walkover' => 'W.O.',
            'started' => 'Iniciado',
            default => $match->matchday_date_id ? 'Programado' : 'Pendiente',
        };
    }

    private function roundLabel($match): string
    {
        $label = $match->round_number
            ? 'Fecha '.$match->round_number
            : ($match->tie_number ? $match->stage.' - Llave '.$match->tie_number : $match->stage);

        return $label.($match->leg_number > 1 ? ' - Vuelta' : '');
    }

    private function shortStatusLabel($match): string
    {
        return match ($match->report?->status) {
            'completed' => 'Fin.',
            'walkover' => 'W.O.',
            'started' => 'Ini.',
            default => $match->matchday_date_id ? 'Prog.' : 'Pend.',
        };
    }

    private function scheduleLabel($match): string
    {
        if (! $match->matchdayDate) {
            return '-';
        }

        $date = $match->matchdayDate->date?->format('d/m') ?? '-';
        $time = $match->scheduled_time
            ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i')
            : '--:--';

        return $date.' '.$time;
    }

    private function patternsHtml(Collection $patterns): string
    {
        $html = '
            <h1 style="font-size:14px;color:#1d4f91;margin:0;text-align:center;">Plantillas de fixture</h1>
            <p style="font-size:8px;color:#667085;text-align:center;">
                Combinaciones publicas de 3 a 12 equipos. Se usa el mismo metodo circular del generador.
            </p>
        ';

        foreach ($patterns->chunk(2) as $patternRow) {
            $html .= '<table cellpadding="3" cellspacing="0" style="width:100%;"><tr>';

            foreach ($patternRow as $pattern) {
                $html .= '<td style="width:50%;vertical-align:top;">'.$this->patternBoxHtml($pattern).'</td>';
            }

            if ($patternRow->count() === 1) {
                $html .= '<td style="width:50%;"></td>';
            }

            $html .= '</tr></table>';
        }

        return $html;
    }

    private function patternBoxHtml(array $pattern): string
    {
        $title = $pattern['team_count'].' equipos'.($pattern['has_bye'] ? ' - con fecha libre' : '');
        $html = '
            <table cellpadding="2" cellspacing="0" border="1" style="width:100%;border-color:#d8dee9;">
                <tr style="background-color:#eef3f9;">
                    <td colspan="2" style="text-align:center;">
                        <strong style="font-size:9px;color:#1d4f91;">'.e($title).'</strong>
                    </td>
                </tr>
        ';

        foreach ($pattern['rounds'] as $round) {
            $matches = collect($round['pairs'])
                ->map(fn (array $pair): string => e($pair['home']).' vs '.e($pair['away']))
                ->implode(' &nbsp; ');

            if ($round['bye']) {
                $matches .= ' &nbsp; <span style="color:#667085;">Libre: '.e($round['bye']).'</span>';
            }

            $html .= '
                <tr>
                    <td style="width:22%;text-align:center;background-color:#f8fafc;">
                        <strong style="font-size:8px;">Fecha '.e((string) $round['number']).'</strong>
                    </td>
                    <td style="width:78%;text-align:center;font-size:8px;">'.$matches.'</td>
                </tr>
            ';
        }

        return $html.'</table><br>';
    }
}
