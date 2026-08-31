<?php
namespace App\Models;
use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;
class MatchdayCourtFeeStatement extends Model implements Auditable
{
    use AuditsCompanyChanges;
    public const PENDING = 'pending';
    public const CONSOLIDATED = 'consolidated';
    public const NEEDS_RECONSOLIDATION = 'needs_reconsolidation';
    protected $fillable = ['company_id','matchday_id','status','revision','consolidated_at','consolidated_by','source_changed_at'];
    protected function casts(): array { return ['revision'=>'integer','consolidated_at'=>'datetime','source_changed_at'=>'datetime']; }
    public function matchday(): BelongsTo { return $this->belongsTo(Matchday::class); }
}
