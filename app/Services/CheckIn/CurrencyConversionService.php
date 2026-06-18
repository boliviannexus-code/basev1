<?php

namespace App\Services\CheckIn;

use App\Models\ExchangeRate;
use Illuminate\Validation\ValidationException;

class CurrencyConversionService
{
    public function normalizeStayPrices(array $stay, ?int $companyId = null): array
    {
        $currency = $stay['currency'] ?? 'BOB';
        $exchangeRate = filled($stay['exchange_rate'] ?? null) ? (float) $stay['exchange_rate'] : null;
        $priceBob = filled($stay['price_per_night_bob'] ?? null) ? (float) $stay['price_per_night_bob'] : null;
        $priceUsd = filled($stay['price_per_night_usd'] ?? null) ? (float) $stay['price_per_night_usd'] : null;
        $exchangeRate ??= $companyId ? ExchangeRate::currentRateForCompany($companyId) : null;

        if ($currency === 'BOB' && $priceBob === null && $priceUsd === null) {
            throw ValidationException::withMessages(['stays' => 'Ingresa precio por noche en BOB.']);
        }

        if ($currency === 'USD' && $priceUsd === null) {
            throw ValidationException::withMessages(['stays' => 'Ingresa precio por noche en USD.']);
        }

        if ($currency === 'BOB' && $priceBob === null && $priceUsd !== null) {
            if ($exchangeRate === null || $exchangeRate <= 0) {
                throw ValidationException::withMessages(['stays' => 'Configura un tipo de cambio vigente para guardar BOB y USD.']);
            }

            $priceBob = round($priceUsd * $exchangeRate, 2);
        }

        if ($currency === 'BOB' && $priceBob !== null && $priceUsd === null) {
            if ($exchangeRate === null || $exchangeRate <= 0) {
                throw ValidationException::withMessages(['stays' => 'Configura un tipo de cambio vigente para guardar BOB y USD.']);
            }

            $priceUsd = round($priceBob / $exchangeRate, 2);
        }

        if ($priceBob === null || $priceUsd === null) {
            if ($exchangeRate === null || $exchangeRate <= 0) {
                throw ValidationException::withMessages(['stays' => 'Configura un tipo de cambio vigente para guardar BOB y USD.']);
            }

            if ($priceBob === null && $priceUsd !== null) {
                $priceBob = round($priceUsd * $exchangeRate, 2);
            }

            if ($priceUsd === null && $priceBob !== null) {
                $priceUsd = round($priceBob / $exchangeRate, 2);
            }
        }

        return [
            ...$stay,
            'price_per_night_bob' => $priceBob,
            'price_per_night_usd' => $priceUsd,
            'exchange_rate' => $exchangeRate,
            'currency' => $currency,
        ];
    }
}
