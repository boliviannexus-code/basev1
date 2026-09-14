<?php
namespace App\Services;
use App\Models\Matchday;
use App\Models\MatchdayCourtFeeStatement;
use App\Models\MatchReport;
class MatchdayCourtFeeStatementService
{
    public function forMatchday(Matchday $matchday): MatchdayCourtFeeStatement
    {
        return MatchdayCourtFeeStatement::query()->firstOrCreate(['matchday_id'=>$matchday->id], ['company_id'=>$matchday->company_id,'status'=>MatchdayCourtFeeStatement::PENDING]);
    }
    public function markSourceChanged(MatchReport $report): void
    {
        $report->loadMissing('fixtureMatch.matchdayDate.matchday');
        $matchday = $report->fixtureMatch?->matchdayDate?->matchday;
        if (! $matchday) return;
        $statement = $this->forMatchday($matchday);
        if ($statement->status === MatchdayCourtFeeStatement::CONSOLIDATED) {
            $statement->update(['status'=>MatchdayCourtFeeStatement::NEEDS_RECONSOLIDATION,'source_changed_at'=>now()]);
        }
    }
    public function isLocked(Matchday $matchday): bool { return $this->forMatchday($matchday)->status !== MatchdayCourtFeeStatement::PENDING; }
}
