<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Verifikasi OTP &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full hero-gradient">
<div class="min-h-screen grid place-items-center p-6">
    <div class="card max-w-md w-full overflow-hidden">
        <div class="p-6 bg-gradient-to-br from-brand-600 to-brand-800 text-white text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-white/20 grid place-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-7 h-7">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1 class="text-lg font-bold">Verifikasi Dua Langkah</h1>
            <p class="text-brand-100 text-sm mt-1">Kode dikirim via {{ strtoupper($channel) }} ke <strong>{{ $destination }}</strong></p>
        </div>

        <form method="POST" action="{{ route('otp.verify') }}" class="p-6 space-y-4"
              x-data="{ code: '' }">
            @csrf
            @if(session('success'))<div class="rounded-lg bg-emerald-50 border border-emerald-200 px-3 py-2 text-sm text-emerald-700">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="rounded-lg bg-rose-50 border border-rose-200 px-3 py-2 text-sm text-rose-700">{{ session('error') }}</div>@endif

            @if(session('dev_otp'))
                <div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                    <strong>Mode Dev:</strong> kode OTP Anda adalah <code class="font-mono">{{ session('dev_otp') }}</code>
                </div>
            @endif

            <label class="block">
                <span class="label">Masukkan kode 6 digit</span>
                <input type="text" name="code" x-model="code"
                       inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" autofocus
                       class="input text-center text-2xl font-bold tracking-[0.5em] uppercase"
                       placeholder="• • • • • •">
                @error('code')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </label>

            <div class="text-xs text-ink-500 text-center">
                Kadaluwarsa pada <strong>{{ $expiresAt->format('H:i:s') }}</strong>
            </div>

            <button class="btn-primary w-full text-base py-3" :disabled="code.length < 4">Verifikasi</button>

            <div class="flex items-center justify-between text-xs">
                <form method="POST" action="{{ route('otp.resend') }}">
                    @csrf
                    <button type="submit" class="text-brand-600 hover:underline">Kirim ulang kode</button>
                </form>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-rose-600 hover:underline">Batalkan & keluar</button>
                </form>
            </div>
        </form>
    </div>
</div>
</body>
</html>
