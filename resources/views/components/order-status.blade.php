@props(['status'])

@php
    $map = [
        'pending_payment' => ['badge-amber', 'Awaiting payment'],
        'paid' => ['badge-blue', 'Paid'],
        'provisioning' => ['badge-blue', 'Provisioning'],
        'completed' => ['badge-green', 'Completed'],
        'failed' => ['badge-red', 'Failed'],
        'canceled' => ['badge-gray', 'Canceled'],
        'expired' => ['badge-gray', 'Expired'],
    ];
    [$class, $label] = $map[$status] ?? ['badge-gray', str($status)->headline()];
@endphp

<span class="{{ $class }}">{{ $label }}</span>
