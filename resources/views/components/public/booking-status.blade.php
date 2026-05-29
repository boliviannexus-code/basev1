@props(['status'])
@php
    $classes = [
        'pending' => 'bg-warning-lt text-warning',
        'confirmed' => 'bg-success-lt text-success',
        'cancelled' => 'bg-danger-lt text-danger',
        'completed' => 'bg-primary-lt text-primary',
    ];
    $labels = \App\Models\TourBooking::STATUSES;
@endphp

<span class="badge {{ $classes[$status] ?? 'bg-secondary-lt text-secondary' }}">
    {{ $labels[$status] ?? ucfirst((string) $status) }}
</span>
