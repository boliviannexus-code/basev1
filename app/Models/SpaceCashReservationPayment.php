<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceCashReservationPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'space_cash_register_id',
        'user_id',
        'account_statement_id',
        'account_statement_item_id',
        'reservation_group_id',
        'payment_method_id',
        'receipt_number',
        'reference',
        'amount_original',
        'currency_original',
        'exchange_rate',
        'amount_bob',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_original' => 'decimal:2',
            'exchange_rate' => 'decimal:4',
            'amount_bob' => 'decimal:2',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(SpaceCashRegister::class, 'space_cash_register_id');
    }

    public function reservationGroup(): BelongsTo
    {
        return $this->belongsTo(ReservationGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }
}
