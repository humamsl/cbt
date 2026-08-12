<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Terkunci &middot; {{ config('app.name') }}</title>
    {{-- Sengaja tanpa @vite / aset eksternal: halaman kunci harus tetap tampil
         utuh walau build aset diutak-atik. Semua gaya inline. --}}
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
            font-family:system-ui,-apple-system,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
            background:linear-gradient(135deg,#0f172a,#1e293b);color:#e2e8f0}
        .card{max-width:560px;width:100%;background:#111827;border:1px solid #1f2937;
            border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.45)}
        .head{padding:32px;text-align:center;background:linear-gradient(135deg,#e11d48,#9f1239)}
        .head .ic{width:64px;height:64px;border-radius:9999px;background:rgba(255,255,255,.15);
            display:grid;place-items:center;margin:0 auto 14px}
        .head h1{margin:0;font-size:20px;color:#fff;font-weight:700}
        .body{padding:32px;text-align:center;line-height:1.65}
        .body p{margin:0 0 16px;color:#cbd5e1;font-size:15px}
        .body strong{color:#fff}
        .meta{margin-top:22px;font-size:12px;color:#64748b}
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
            <h1>Aplikasi Terkunci</h1>
        </div>
        <div class="body">
            <p>Verifikasi integritas hak cipta <strong>{{ \App\Support\CopyrightGuard::owner() }}</strong> gagal.</p>
            <p>Teks hak cipta pada aplikasi ini telah diubah atau dihapus. Untuk melindungi
               hak cipta pemilik, seluruh aplikasi dinonaktifkan.</p>
            <p>Kembalikan teks hak cipta <strong>{{ \App\Support\CopyrightGuard::owner() }}</strong>
               seperti semula untuk membuka kembali aplikasi.</p>
            <div class="meta">
                {{ config('app.name') }} &middot; {{ now()->format('d-m-Y H:i') }}
            </div>
        </div>
    </div>
</body>
</html>
