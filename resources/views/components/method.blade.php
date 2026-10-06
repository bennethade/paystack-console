@props(['method'])
<span {{ $attributes->class(['method', 'method-'.strtoupper($method)]) }}>{{ strtoupper($method) }}</span>
