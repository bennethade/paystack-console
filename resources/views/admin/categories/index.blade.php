@extends('layouts.app')
@section('title', 'Endpoint categories')

@section('content')
@include('admin.partials.nav')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold text-ink-100">Endpoint categories</h1>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Add category</a>
    </div>
    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Order</th><th>Name</th><th>Slug</th><th>Endpoints</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="font-mono text-xs">{{ $category->sort_order }}</td>
                    <td>{{ $category->name }}</td>
                    <td class="font-mono text-xs">{{ $category->slug }}</td>
                    <td class="text-xs"><a class="text-brand-400 hover:underline" href="{{ route('admin.endpoints.index', ['category' => $category->id]) }}">{{ $category->enabled_endpoints_count }} / {{ $category->endpoints_count }}</a></td>
                    <td>{!! $category->is_enabled ? '<span class="badge badge-success">Enabled</span>' : '<span class="badge badge-neutral">Disabled</span>' !!}</td>
                    <td class="text-right"><a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-secondary btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
