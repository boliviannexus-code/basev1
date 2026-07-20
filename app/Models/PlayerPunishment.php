<?php

namespace App\Models;

use App\Models\Concerns\AuditsCompanyChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class PlayerPunishment extends Model implements Auditable
{
    use AuditsCompanyChanges;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_LIFT_REQUESTED = 'lift_requested';
    public const STATUS_LIFTED = 'lifted';

    protected $fillable = [
        'company_id',
        'player_id',
        'red_card_article_id',
        'duration_type',
        'duration_value',
        'starts_on',
        'ends_on',
        'reason',
        'status',
        'lift_reason',
        'lift_requested_at',
        'lift_requested_by',
        'lift_review_note',
        'lift_reviewed_at',
        'lift_reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'duration_value' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'lift_requested_at' => 'datetime',
            'lift_reviewed_at' => 'datetime',
        ];
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(RedCardArticle::class, 'red_card_article_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lift_requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lift_reviewed_by');
    }
}
