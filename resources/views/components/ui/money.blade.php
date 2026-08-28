@props([
    'amount',
    'currency' => 'BOB',
    'exchangeRate' => null,
])

@php
    $currency = strtoupper((string) $currency);
    $currency = $currency === 'BS' ? 'BOB' : $currency;
    $amount = (float) $amount;
    $rate = (float) $exchangeRate;
    $isUsd = $currency === 'USD';
    $primary = money_format_decimal($amount).' '.$currency;
    $reference = $rate > 0
        ? ($isUsd
            ? money_format_decimal($amount * $rate).' BOB'
            : money_format_decimal($amount / $rate).' USD')
        : null;
@endphp

<span {{ $attributes->class(['d-inline-flex flex-column lh-sm']) }}>
    <span>{{ $primary }}</span>
    @if ($reference)
        <small class="text-body-secondary mt-1">≈ {{ $reference }}</small>
    @endif
</span>
