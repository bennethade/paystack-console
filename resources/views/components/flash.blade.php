@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any() && ! ($inline ?? false))
    <div class="mb-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-300" role="alert">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
