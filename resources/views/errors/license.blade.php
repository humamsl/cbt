<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lisensi Diperlukan &middot; {{ config('app.name') }}</title>
    {{-- Halaman kunci sengaja tanpa aset eksternal (semua gaya inline). --}}
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            background:linear-gradient(135deg,#0f172a,#1e293b);color:#e2e8f0}
        .card{max-width:600px;width:100%;background:#111827;border:1px solid #1f2937;
            border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.45)}
        .head{padding:32px;text-align:center;background:linear-gradient(135deg,#d97706,#b45309)}
        .head .ic{width:64px;height:64px;border-radius:9999px;background:rgba(255,255,255,.15);
            display:grid;place-items:center;margin:0 auto 14px}
        .head h1{margin:0;font-size:20px;color:#fff;font-weight:700}
        .body{padding:32px;line-height:1.65}
        .body p{margin:0 0 16px;color:#cbd5e1;font-size:15px;text-align:center}
        .reason{margin:0 auto 20px;max-width:90%;text-align:center;color:#fca5a5;font-weight:600}
        .kv{background:#0b1220;border:1px solid #1f2937;border-radius:10px;padding:14px 16px;font-size:12.5px}
        .kv div{display:flex;justify-content:space-between;gap:12px;padding:4px 0}
        .kv span{color:#64748b}
        .kv code{color:#e2e8f0;word-break:break-all;text-align:right}
        .meta{margin-top:20px;font-size:12px;color:#64748b;text-align:center}
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <div class="ic">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24"
                     fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h1>Lisensi Diperlukan</h1>
        </div>
        <div class="body">
            <p class="reason">{{ $reason ?? 'Lisensi tidak valid.' }}</p>
            <p>Aplikasi ini hanya dapat dijalankan pada instalasi yang berlisensi resmi
               dari <strong>{{ config('license.owner') }}</strong>. Hubungi pemilik aplikasi
               untuk menerbitkan lisensi bagi server ini.</p>
            <div class="kv">
                <div><span>Aplikasi</span><code>{{ config('license.app') }}</code></div>
                <div><span>Domain</span><code>{{ $host ?? '-' }}</code></div>
                <div><span>Sidik jari mesin</span><code>{{ $fingerprint ?? '-' }}</code></div>
                <div><span>Waktu server</span><code>{{ now()->format('d-m-Y H:i') }}</code></div>
            </div>
            <div class="meta">
                Sampaikan Domain &amp; Sidik jari mesin di atas kepada pemilik aplikasi
                untuk mendapatkan lisensi.
            </div>
        </div>
    </div>
</body>
</html>
