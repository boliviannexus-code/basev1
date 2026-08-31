<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use TCPDF;

class MatchdayCourtFeePdfService
{
    public function render(array $context): Response
    {
        $matchday = $context['matchday'];
        $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator(config('app.name', 'Nexgol'));
        $pdf->SetAuthor(config('app.name', 'Nexgol'));
        $pdf->SetTitle('Derecho de cancha - '.($matchday->name ?: 'Jornada '.$matchday->number));
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 8);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont('helvetica', '', 7);
        $pdf->AddPage();
        $pdf->writeHTML($this->html($context), true, false, true, false, '');

        $filename = 'derecho-cancha-'.Str::slug($matchday->name ?: 'jornada-'.$matchday->number).'.pdf';
        return response($pdf->Output($filename, 'S'), 200, ['Content-Disposition'=>'inline; filename="'.$filename.'"','Content-Type'=>'application/pdf']);
    }

    private function html(array $context): string
    {
        extract($context);
        $headers = '<th style="width:18%;">Equipo</th><th style="width:14%;">Torneo</th>';
        $dynamic = $extraChargeColumns->count() + $accountChargeColumns->count();
        $dynamicWidth = $dynamic > 0 ? 53 / $dynamic : 0;
        foreach ($extraChargeColumns as $column) $headers .= '<th style="width:'.$dynamicWidth.'%;text-align:right;">'.e($column->name).'</th>';
        foreach ($accountChargeColumns as $column) $headers .= '<th style="width:'.$dynamicWidth.'%;text-align:right;">'.e($column->description).'</th>';
        $headers .= '<th style="width:15%;text-align:right;">Total (Bs)</th>';
        $rows = '';
        foreach ($teamCharges as $charge) {
            $extras = $extraInstallments->get($charge['key'], collect());
            $pending = $accountCharges->get($charge['key'], collect());
            $total = $charge['total'] + (float)$extras->sum('amount') + (float)$pending->sum('total_amount');
            $cells = '<td>'.e($charge['team']->name).'</td><td>'.e($charge['tournament']?->name ?? '-').'</td>';
            foreach ($extraChargeColumns as $column) {
                $item = $extras->firstWhere('extra_charge_id', $column->id);
                $cells .= '<td style="text-align:right;">'.($item ? 'Bs '.number_format((float)$item->amount,2,',','.').'<br><small>Cuota '.$item->installment_number.'/'.$item->extraCharge->installments.'</small>' : '-').'</td>';
            }
            foreach ($accountChargeColumns as $column) {
                $items = $pending->where('concept_key', $column->concept_key);
                $cells .= '<td style="text-align:right;">'.($items->isNotEmpty() ? 'Bs '.number_format((float)$items->sum('total_amount'),2,',','.').'<br><small>'.$items->sum('quantity').' unidad(es)</small>' : '-').'</td>';
            }
            $rows .= '<tr>'.$cells.'<td style="text-align:right;font-weight:bold;">'.number_format($total,2,',','.').'</td></tr>';
        }
        $grandTotal = (float)$teamCharges->sum('total') + (float)$extraInstallments->flatten(1)->sum('amount') + (float)$accountCharges->flatten(1)->sum('total_amount');
        return '<h1 style="text-align:center;font-size:16px;">Derecho de cancha consolidado</h1>
            <table cellpadding="2"><tr><td><strong>Liga:</strong> '.e($matchday->company?->name ?? '-').'</td><td><strong>Jornada:</strong> '.e($matchday->name ?: 'Jornada '.$matchday->number).'</td><td><strong>Gestión:</strong> '.e($matchday->season?->name ?? '-').'</td></tr>
            <tr><td><strong>Revisión:</strong> '.$statement->revision.'</td><td><strong>Consolidado:</strong> '.e($statement->consolidated_at?->format('d/m/Y H:i') ?? '-').'</td><td></td></tr></table><br>
            <table border="1" cellpadding="4"><thead><tr style="background-color:#e9ecef;font-weight:bold;">'.$headers.'</tr></thead><tbody>'.$rows.'</tbody><tfoot><tr style="font-weight:bold;background-color:#f3f4f6;"><td colspan="'.(2+$dynamic).'">TOTAL GENERAL</td><td style="text-align:right;">Bs '.number_format($grandTotal,2,',','.').'</td></tr></tfoot></table>';
    }
}
