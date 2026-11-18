@props([
    'amount',
    'currency' => 'BOB',
    'exchangeRate' => null,
    'inline' => false,
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

<span {{ $attributes->class(['d-inline-flex lh-sm', 'flex-column' => ! $inline, 'flex-row flex-wrap align-items-baseline gap-2' => $inline]) }}>
    <span @class(['text-success' => $isUsd])>{{ $primary }}</span>
    @if ($reference)
        <small @class(['text-success' => ! $isUsd, 'text-body-secondary' => $isUsd, 'mt-1' => ! $inline])>≈ {{ $reference }}</small>
    @endif
</span>
