<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtraChargeAssignment extends Model
{
    protected $fillable = ['extra_charge_id', 'tournament_id', 'team_id', 'all_teams'];
    protected function casts(): array { return ['all_teams' => 'boolean']; }
    public function extraCharge(): BelongsTo { return $this->belongsTo(ExtraCharge::class); }
    public function tournament(): BelongsTo { return $this->belongsTo(Tournament::class); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
}
