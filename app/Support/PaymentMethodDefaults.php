<?php

namespace App\Support;

use App\Models\PaymentMethod;

class PaymentMethodDefaults
{
    public static function ensureForCompany(?int $companyId): void
    {
        if (! $companyId) {
            return;
        }

        foreach (['Efectivo', 'QR'] as $name) {
            PaymentMethod::query()->firstOrCreate(
                ['company_id' => $companyId, 'name' => $name],
                ['is_active' => true],
            );
        }
    }
}
