<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Reservation Advance
    |--------------------------------------------------------------------------
    |
    | The public QR payment flow is not implemented yet, but reservations already
    | store the required advance and pending balance. Use "percentage" for a
    | percentage of the total or "fixed" for a fixed amount capped by total.
    |
    */

    'advance' => [
        'type' => env('RESERVATION_ADVANCE_TYPE', 'percentage'),
        'percentage' => (float) env('RESERVATION_ADVANCE_PERCENTAGE', 50),
        'fixed_amount' => (float) env('RESERVATION_ADVANCE_FIXED_AMOUNT', 0),
    ],

    'temporary_hold_minutes' => (int) env('RESERVATION_TEMPORARY_HOLD_MINUTES', 60),
];
