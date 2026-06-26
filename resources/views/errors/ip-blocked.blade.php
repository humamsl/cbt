<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akses IP Diblokir &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full hero-gradient">
<div class="min-h-screen grid place-items-center p-6">
    <div class="card max-w-lg w-full overflow-hidden text-center">
        <div class="p-8 bg-gradient-to-br from-rose-600 to-rose-700 text-white">
            <div class="mx-auto w-16 h-16 rounded-full bg-white/20 grid place-items-center mb-3 text-3xl">🌐</div>
            <h1 class="text-xl font-bold">Akses Ditolak</h1>
            <p class="text-rose-100 text-sm mt-1">IP Anda tidak diizinkan mengakses ujian</p>
        </div>
        <div class="p-6 space-y-4 text-left">
            <div class="rounded-lg bg-slate-50 border border-slate-200 p-3 text-sm">
                <div class="font-semibold text-ink-700 mb-1">IP Anda saat ini</div>
                <div class="font-mono text-rose-600 text-base">{{ $clientIp }}</div>
            </div>

            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3 text-sm">
                <div class="font-semibold text-emerald-800 mb-1">IP yang diizinkan</div>
                <ul class="font-mono text-xs text-emerald-700 list-disc pl-5 space-y-0.5">
                    @foreach($rules as $r)
                        <li>{{ $r }}</li>
                    @endforeach
                </ul>
            </div>

            <p class="text-xs text-ink-500">
                Ujian hanya dapat dikerjakan dari jaringan yang sudah diatur oleh administrator
                sekolah. Hubungi pengawas / admin untuk informasi lebih lanjut.
            </p>

            <a href="{{ route('dashboard') }}" class="btn-secondary w-full justify-center">← Kembali ke Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
