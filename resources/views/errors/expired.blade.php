<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judul ?? 'Lisensi Berakhir' }} &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full hero-gradient">
<div class="min-h-screen grid place-items-center p-6">
    <div class="card max-w-lg w-full overflow-hidden text-center">
        <div class="p-8 bg-gradient-to-br from-rose-600 to-rose-700 text-white">
            <div class="mx-auto w-16 h-16 rounded-full bg-white/20 grid place-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-9 h-9">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold">{{ $judul ?? 'Masa Berlaku Habis' }}</h1>
        </div>
        <div class="p-8">
            <p class="text-ink-600">{{ $pesan ?? 'Aplikasi telah mencapai batas waktu penggunaan.' }}</p>
            <div class="mt-6 text-xs text-ink-500">
                Aplikasi: <strong>{{ config('app.name') }}</strong><br>
                Waktu Server: {{ now()->translatedFormat('d F Y H:i') }}
            </div>
        </div>
    </div>
</div>
</body>
</html>
