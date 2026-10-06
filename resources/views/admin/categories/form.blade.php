@extends('layouts.app')
@section('title', $category->exists ? 'Edit '.$category->name : 'Add category')

@section('content')
@include('admin.partials.nav')
<form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="mx-auto max-w-2xl space-y-5">
    @csrf
    @if ($category->exists) @method('PUT') @endif
    <h1 class="text-xl font-semibold text-ink-100">{{ $category->exists ? 'Edit '.$category->name : 'Add category' }}</h1>
    <div class="card card-body grid gap-4 sm:grid-cols-2">
        <div><label for="name" class="label">Name</label><input id="name" name="name" value="{{ old('name', $category->name) }}" class="input" required></div>
        <div><label for="slug" class="label">Slug (URL segment)</label><input id="slug" name="slug" value="{{ old('slug', $category->slug) }}" class="input font-mono" required></div>
        <div class="sm:col-span-2"><label for="description" class="label">Description</label><textarea id="description" name="description" rows="2" class="input">{{ old('description', $category->description) }}</textarea></div>
        <div class="sm:col-span-2"><label for="docs_url" class="label">Documentation URL</label><input id="docs_url" name="docs_url" type="url" value="{{ old('docs_url', $category->docs_url) }}" class="input"></div>
        <div><label for="sort_order" class="label">Sort order</label><input id="sort_order" name="sort_order" type="number" value="{{ old('sort_order', $category->sort_order ?? 0) }}" class="input"></div>
        <div class="flex items-end"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $category->is_enabled))> Enabled (hides all its endpoints when off)</label></div>
    </div>
    <div class="flex gap-2"><button class="btn btn-primary">Save</button><a href="{{ route('admin.categories.index') }}" class="btn btn-ghost">Cancel</a></div>
</form>
@endsection
