@props(['code' => null, 'success' => null])
@php
    $class = match (true) {
        $code === null => 'badge-danger',
        $code >= 200 && $code < 300 => $success === false ? 'badge-warning' : 'badge-success',
        $code >= 400 && $code < 500 => 'badge-warning',
        default => 'badge-danger',
    };
@endphp
<span {{ $attributes->class(['badge font-mono', $class]) }}>{{ $code ?? 'ERR' }}</span>
