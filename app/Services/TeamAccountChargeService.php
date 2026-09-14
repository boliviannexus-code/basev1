<?php
namespace App\Services;
use App\Models\LeagueSetting;
use App\Models\MatchControlItem;
use App\Models\MatchReport;
use App\Models\TeamAccountCharge;
class TeamAccountChargeService
{
    public function syncFromReport(MatchReport $report): void
    {
        $report->loadMissing(['fixtureMatch.matchdayDate.matchday', 'players']);
        $match = $report->fixtureMatch;
        if (! $match?->matchdayDate?->matchday) return;
        $existingApplied = TeamAccountCharge::query()->where('source_match_report_id', $report->id)->where('status', 'applied')->exists();
        if ($existingApplied) return;
        TeamAccountCharge::query()->where('source_match_report_id', $report->id)->where('status', 'pending')->delete();
        $items = MatchControlItem::query()->where('company_id', $report->company_id)->get();
        $costs = collect($report->control_item_costs ?? []);
        foreach (['home' => $match->home_team_id, 'away' => $match->away_team_id] as $side => $teamId) {
            if (! $teamId) continue;
            foreach ($items as $item) {
                if ((bool) data_get($report->control_items, $side.'.'.$item->key, true)) continue;
                $amount = (float) $costs->get($item->key, $item->absence_cost);
                if ($amount <= 0) continue;
                $this->create($report, (int)$teamId, 'absence:'.$item->key, 'Ausencia: '.$item->label, 1, $amount);
            }
            $yellowCards = (int) $report->players->where('team_id', $teamId)->sum('yellow_cards');
            $yellowFee = (float) (LeagueSetting::query()->where('company_id', $report->company_id)->value('yellow_card_fee') ?? 0);
            if ($yellowCards > 0 && $yellowFee > 0) $this->create($report, (int)$teamId, 'yellow_cards', 'Tarjetas amarillas', $yellowCards, $yellowFee);
        }
    }
    private function create(MatchReport $report, int $teamId, string $key, string $description, int $quantity, float $unit): void
    {
        $match = $report->fixtureMatch;
        TeamAccountCharge::query()->create(['company_id'=>$report->company_id,'team_id'=>$teamId,'tournament_id'=>$match->tournament_id,'source_matchday_id'=>$match->matchdayDate->matchday_id,'source_match_report_id'=>$report->id,'concept_key'=>$key,'description'=>$description,'quantity'=>$quantity,'unit_amount'=>$unit,'total_amount'=>round($quantity*$unit,2),'status'=>'pending']);
    }
}
