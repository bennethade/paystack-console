@props(['environment'])
@if ($environment === 'live')
    <span {{ $attributes->class(['badge border-red-500 bg-red-600 text-white']) }}>Live</span>
@else
    <span {{ $attributes->class(['badge badge-warning']) }}>Test</span>
@endif
