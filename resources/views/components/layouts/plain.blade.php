@props(['title' => null])

{{-- A standalone page with no store chrome: used by the platform placeholder
     and by the "unknown store" and "store suspended" notices. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="{{ asset('favicon.png') }}" sizes="any">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-100">
    <div class="mx-auto flex min-h-full max-w-lg items-center px-4 py-10">
        <div class="card w-full px-6 py-8">
            {{ $slot }}
        </div>
    </div>
</body>
</html>
