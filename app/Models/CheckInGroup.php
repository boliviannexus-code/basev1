<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CheckInGroup extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    public const STATUSES = [
        'checked_in',
        'checked_out',
        'cancelled',
    ];

    protected $fillable = [
        'company_id',
        'code',
        'main_guest_id',
        'reservation_channel_id',
        'total_people',
        'check_in_date',
        'check_out_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_people' => 'integer',
            'check_in_date' => 'date',
            'check_out_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CheckInGroup $group): void {
            if (! filled($group->code) && filled($group->company_id)) {
                $group->code = self::nextCode((int) $group->company_id);
            }
        });
    }

    public static function nextCode(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $prefix = 'CI-'.now()->format('Ymd').'-';
            $lastCode = self::query()
                ->withoutGlobalScope('company')
                ->where('company_id', $companyId)
                ->where('code', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('code')
                ->value('code');

            $next = $lastCode ? ((int) substr($lastCode, -4)) + 1 : 1;

            return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function mainGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'main_guest_id');
    }

    public function reservationChannel(): BelongsTo
    {
        return $this->belongsTo(ReservationChannel::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stays(): HasMany
    {
        return $this->hasMany(Stay::class);
    }
}
