<?php
namespace App\Models;
use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
class TeamAccountCharge extends Model implements Auditable
{
    use AuditsCompanyChanges;
    protected $fillable = ['company_id','team_id','tournament_id','source_matchday_id','source_match_report_id','concept_key','description','quantity','unit_amount','total_amount','status','applied_matchday_id','applied_at'];
    protected function casts(): array { return ['quantity'=>'integer','unit_amount'=>'decimal:2','total_amount'=>'decimal:2','applied_at'=>'datetime']; }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function sourceMatchday(): BelongsTo { return $this->belongsTo(Matchday::class, 'source_matchday_id'); }
}
