<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ReservationGroup extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'reservation_channel_id',
        'code',
        'guest_name',
        'guest_email',
        'guest_phone',
        'guest_document',
        'check_in',
        'check_out',
        'nights',
        'guests',
        'subtotal_amount',
        'total_amount',
        'advance_amount',
        'balance_amount',
        'currency',
        'status',
        'payment_status',
        'payment_method',
        'payment_reference',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'nights' => 'integer',
            'guests' => 'integer',
            'subtotal_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'advance_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReservationGroup $group): void {
            if (! filled($group->code) && filled($group->company_id)) {
                $group->code = self::nextCode((int) $group->company_id);
            }
        });
    }

    public static function nextCode(int $companyId): string
    {
        return DB::transaction(function () use ($companyId): string {
            $prefix = 'RSG-'.now()->format('Ymd').'-';
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

    public function reservationChannel(): BelongsTo
    {
        return $this->belongsTo(ReservationChannel::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function accountStatement(): HasOne
    {
        return $this->hasOne(AccountStatement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
