@if (session('status'))
    <div class="mb-5 flex items-start gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
        <span class="mt-0.5">✓</span>
        <p>{{ session('status') }}</p>
    </div>
@endif

@if (session('error'))
    <div class="mb-5 flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <span class="mt-0.5">!</span>
        <p>{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    <div class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="mt-1.5 list-inside list-disc space-y-0.5">
            @foreach ($errors->unique() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
