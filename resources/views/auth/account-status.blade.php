<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul }} &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full hero-gradient">
@php
    $grad = [
        'rose'  => 'from-rose-600 to-rose-700',
        'amber' => 'from-amber-600 to-amber-700',
        'sky'   => 'from-sky-600 to-sky-700',
    ][$tone ?? 'rose'];
@endphp
<div class="min-h-screen grid place-items-center p-6">
    <div class="card max-w-md w-full overflow-hidden text-center">
        <div class="p-8 bg-gradient-to-br {{ $grad }} text-white">
            <div class="mx-auto w-16 h-16 rounded-full bg-white/20 grid place-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9">
                    <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold">{{ $judul }}</h1>
        </div>
        <div class="p-8">
            <p class="text-ink-600">{{ $pesan }}</p>
            <a href="{{ route('login') }}" class="btn-secondary mt-6 inline-flex">Kembali ke Login</a>
        </div>
    </div>
</div>
</body>
</html>
