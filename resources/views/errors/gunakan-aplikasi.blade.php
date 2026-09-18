<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gunakan Aplikasi CBT &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full hero-gradient">
<div class="min-h-screen grid place-items-center p-4 sm:p-6">
    <div class="card max-w-lg w-full overflow-hidden text-center">
        <div class="p-6 sm:p-8 bg-gradient-to-br from-brand-600 to-brand-800 text-white">
            <div class="mx-auto w-16 h-16 rounded-full bg-white/20 grid place-items-center mb-3 text-3xl">📱</div>
            <h1 class="text-xl font-bold">Ujian Hanya Lewat Aplikasi</h1>
            <p class="text-brand-50 text-sm mt-1">Sekolah Anda mewajibkan ujian dikerjakan lewat Aplikasi CBT Siswa</p>
        </div>
        <div class="p-6 space-y-4 text-left">
            <p class="text-sm text-ink-600">
                Untuk menjaga keamanan &amp; kejujuran ujian, halaman ujian di browser/web
                sudah dinonaktifkan oleh administrator sekolah. Silakan buka
                <strong>Aplikasi CBT Siswa</strong> di HP Anda dan login dengan NISN &amp; password yang sama.
            </p>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-3 text-xs text-amber-800">
                Belum punya aplikasinya? Hubungi guru/admin sekolah untuk mendapatkan file aplikasi (APK).
            </div>
            <a href="{{ route('dashboard') }}" class="btn-secondary w-full justify-center">← Kembali ke Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
