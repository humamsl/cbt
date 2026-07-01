<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $AppCfg['app_name'] }} &middot; {{ $AppCfg['app_tagline'] }}</title>
@if($AppCfg['favicon'])
    <link rel="icon" href="{{ Storage::url($AppCfg['favicon']) }}">
@endif
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800,900" rel="stylesheet" />
<style>
    :root{
        --ink:#181414; --ink-700:#57504f; --ink-500:#8c8482;
        --red:#dc2626; --red-dark:#b91c1c; --amber:#f59e0b; --line:#eee2df;
        --navy:#1e2f5c; --navy-2:#3b5bab;
    }
    *{box-sizing:border-box;}
    body{margin:0;font-family:'Plus Jakarta Sans',system-ui,sans-serif;color:var(--ink);background:#fff;}
    .wrap{max-width:1140px;margin:0 auto;padding:0 24px;}

    /* ===== top bar mengambang di atas hero ===== */
    .topbar{position:absolute;top:0;left:0;right:0;z-index:5;padding:22px 0;}
    .topbar .row{display:flex;align-items:center;justify-content:space-between;}
    .brand{display:flex;align-items:center;gap:10px;}
    .brand .logo-img{width:38px;height:38px;object-fit:contain;background:#fff;border-radius:10px;padding:3px;}
    .brand .logo-fallback{width:38px;height:38px;border-radius:10px;background:var(--red);display:grid;place-items:center;color:#fff;font-weight:800;font-size:15px;}
    .brand .name{font-weight:800;font-size:14px;color:#fff;line-height:1.2;}
    .brand .tag{font-size:10.5px;color:rgba(255,255,255,.75);}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:11px 22px;border-radius:999px;font-weight:800;font-size:13.5px;text-decoration:none;border:none;cursor:pointer;}
    .btn-red{background:var(--red);color:#fff;box-shadow:0 10px 22px -8px rgba(220,38,38,.6);}
    .btn-glass{background:rgba(255,255,255,.14);color:#fff;border:1.5px solid rgba(255,255,255,.35);backdrop-filter:blur(4px);}

    /* ===== hero foto full-bleed ===== */
    .hero{position:relative;margin:0 16px;border-radius:28px;overflow:hidden;min-height:520px;
        @if($AppCfg['login_bg'])
            background:
                linear-gradient(100deg, rgba(10,8,8,.88) 10%, rgba(10,8,8,.55) 45%, rgba(10,8,8,.15) 75%),
                url('{{ Storage::url($AppCfg['login_bg']) }}') center/cover no-repeat;
        @else
            background:
                radial-gradient(circle at 15% 20%, rgba(220,38,38,.35), transparent 55%),
                radial-gradient(circle at 85% 75%, rgba(245,158,11,.25), transparent 55%),
                linear-gradient(135deg, #181414 0%, #2a1616 60%, #3a1a12 100%);
        @endif
    }
    .hero-inner{position:relative;z-index:2;padding:112px 56px 70px;max-width:620px;color:#fff;}
    h1{font-size:40px;font-weight:800;line-height:1.18;margin:0 0 16px;letter-spacing:-.01em;}
    h1 .accent{color:var(--amber);}
    .hero p.lead{font-size:15.5px;color:rgba(255,255,255,.82);line-height:1.7;margin:0 0 26px;max-width:460px;}
    .proof{display:flex;align-items:center;gap:14px;margin-top:30px;}
    .avatars{display:flex;}
    .avatars span{width:34px;height:34px;border-radius:50%;border:2.5px solid #181414;display:grid;place-items:center;
        font-size:11px;font-weight:800;color:#fff;margin-left:-10px;}
    .avatars span:first-child{margin-left:0;}
    .proof .txt b{display:block;font-size:13.5px;}
    .proof .txt span{font-size:11.5px;color:rgba(255,255,255,.7);}

    /* ===== kartu fitur mengambang, overlap bawah hero ===== */
    .float-strip{margin:-56px 40px 0;position:relative;z-index:3;background:#fff;border-radius:22px;
        box-shadow:0 30px 60px -24px rgba(24,20,20,.25);padding:30px 36px;
        display:grid;grid-template-columns:repeat(3,1fr);gap:10px;}
    .float-strip .item{display:flex;align-items:flex-start;gap:14px;padding:0 16px;position:relative;}
    .float-strip .item + .item::before{content:"";position:absolute;left:0;top:6px;bottom:6px;width:1px;background:var(--line);}
    .float-strip .ic{width:42px;height:42px;border-radius:12px;background:#fdece9;color:var(--red);display:grid;place-items:center;font-size:19px;flex-shrink:0;}
    .float-strip b{display:block;font-size:14px;margin-bottom:3px;}
    .float-strip span{font-size:12px;color:var(--ink-500);line-height:1.5;}

    /* ===== section modul — kartu soft navy ===== */
    .modules-v2{padding:100px 0 70px;background:#fff;}
    .m-head{text-align:center;max-width:560px;margin:0 auto 40px;}
    .m-head .kicker{font-size:12px;font-weight:800;letter-spacing:.12em;color:var(--red);text-transform:uppercase;margin-bottom:10px;}
    .m-head h2{font-size:28px;font-weight:800;margin:0 0 8px;color:var(--ink);}
    .m-head p{font-size:14px;color:var(--ink-700);}

    .soft-card{
        background:linear-gradient(150deg,#fbfcfe 0%,#e8ebf1 100%);
        border-radius:22px;
        box-shadow:0 16px 36px -18px rgba(30,47,92,.25), inset 0 1px 0 rgba(255,255,255,.7);
        padding:34px 24px;
        text-align:center;
        position:relative;
        text-decoration:none;
        display:block;
        transition:transform .18s ease, box-shadow .18s ease;
    }
    a.soft-card:hover{transform:translateY(-3px);box-shadow:0 22px 44px -18px rgba(30,47,92,.32), inset 0 1px 0 rgba(255,255,255,.7);}
    .soft-card .ic{width:58px;height:58px;margin:0 auto 14px;color:var(--navy);}
    .soft-card .ic svg{width:100%;height:100%;}
    .soft-card b.title{display:block;font-size:16px;font-weight:800;letter-spacing:.03em;color:var(--navy);margin-bottom:4px;text-transform:uppercase;}
    .soft-card span.sub{display:block;font-size:13px;color:var(--navy-2);}
    .soft-card .pill{position:absolute;top:16px;right:16px;background:#fdece9;color:var(--red);font-size:10px;font-weight:800;
        padding:4px 10px;border-radius:999px;letter-spacing:.03em;}

    .m-primary{margin-bottom:20px;}
    .m-primary .soft-card{padding:44px 24px;}
    .m-primary .ic{width:74px;height:74px;}
    .m-primary b.title{font-size:19px;}
    .m-primary span.sub{font-size:14px;}
    .m-primary .badge-utama{display:inline-block;background:var(--navy);color:#fff;font-size:10px;font-weight:800;
        letter-spacing:.05em;padding:5px 14px;border-radius:999px;margin-bottom:14px;}

    .m-row{display:grid;grid-template-columns:repeat(3,1fr);gap:18px;}
    .soft-card.muted{opacity:.55;filter:grayscale(.3);cursor:default;}
    .soft-card.muted .pill{background:#f1f2f4;color:var(--ink-500);}

    footer{padding:26px 0;color:var(--ink-500);font-size:13px;text-align:center;border-top:1px solid var(--line);margin-top:20px;}

    @media(max-width:860px){
        .hero{min-height:640px;}
        .hero-inner{padding:130px 28px 60px;}
        .float-strip{grid-template-columns:1fr;margin:-40px 20px 0;}
        .float-strip .item + .item::before{display:none;}
        .m-row{grid-template-columns:1fr;}
        h1{font-size:30px;}
    }
</style>
</head>
<body>

<div style="position:relative;">
    <div class="topbar">
        <div class="wrap row">
            <div class="brand">
                @if($AppCfg['logo'])
                    <img src="{{ Storage::url($AppCfg['logo']) }}" alt="" class="logo-img">
                @else
                    <div class="logo-fallback">{{ mb_substr($AppCfg['app_name'], 0, 1) }}</div>
                @endif
                <div>
                    <div class="name">{{ $AppCfg['app_name'] }}</div>
                    <div class="tag">{{ $AppCfg['app_tagline'] }}</div>
                </div>
            </div>
            <a href="#modul" class="btn btn-glass">Pilih Aplikasi ↓</a>
        </div>
    </div>

    <section class="hero">
        <div class="hero-inner">
            <h1>Digital Learning,<br>Management <span class="accent">System</span></h1>
            <p class="lead">Selamat Datang di Sistem Manajemen Pembelajaran Digital SMP Negeri 1 Indonesia. &amp; </p>
            <!-- <a href="#modul" class="btn btn-red">Pilih Aplikasi Anda →</a> -->

            @php $totalOrang = ($stats['siswa'] ?? 0) + ($stats['guru'] ?? 0); @endphp
            <div class="proof">
                <div class="avatars">
                    <span style="background:#dc2626">A</span>
                    <span style="background:#f59e0b">G</span>
                    <span style="background:#181414">S</span>
                    <span style="background:#57504f">+</span>
                </div>
                <div class="txt">
                    @if($totalOrang > 0)
                        <b>Dipercaya {{ number_format($totalOrang) }}+ Siswa &amp; Guru</b>
                    @else
                        <b>Sistem Informasi Sekolah Terintegrasi</b>
                    @endif
                    <span>★ Sistem terintegrasi &amp; aman</span>
                </div>
            </div>
        </div>
    </section>

    <div class="float-strip">
        <div class="item">
            <div class="ic">🔐</div>
            <div><b>SSO Terintegrasi</b><span>Satu akun untuk semua modul</span></div>
        </div>
        <div class="item">
            <div class="ic">🛡</div>
            <div><b>Anti-Cheat 7 Lapis</b><span>Ujian lebih aman &amp; terjaga</span></div>
        </div>
        <div class="item">
            <div class="ic">🔄</div>
            <div><b>Data Selalu Update</b><span>Sinkron real-time antar modul</span></div>
        </div>
    </div>
</div>

<section class="modules-v2" id="modul">
    <div class="wrap">
        <div class="m-head">
            <div class="kicker">Available Apps</div>
            <h2>Digital Learning Management System</h2>
            <p> </p>
        </div>

        <div class="m-primary">
            <a href="{{ route('datacenter.login') }}" class="soft-card">
                <div class="badge-utama"></div>
                <div class="ic">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="24" cy="15" r="6"/>
                        <path d="M12 38c0-7 5-12 12-12s12 5 12 12"/>
                        <circle cx="9" cy="20" r="4"/>
                        <path d="M2 34c0-5 3-8.5 7-9.5"/>
                        <circle cx="39" cy="20" r="4"/>
                        <path d="M46 34c0-5-3-8.5-7-9.5"/>
                    </svg>
                </div>
                <b class="title">Data Center</b>
                <span class="sub">Pusat Data Sekolah — Login khusus Admin</span>
            </a>
        </div>

        <div class="m-row">
            <a href="{{ route('cbt.login') }}" class="soft-card">
                <div class="ic">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="6" y="9" width="36" height="24" rx="3"/>
                        <path d="M18 40h12M24 33v7"/>
                        <path d="M24 15v8l6 4"/>
                    </svg>
                </div>
                <b class="title">CBT</b>
                <span class="sub">Computer Based Test — Admin, Guru &amp; Siswa</span>
            </a>
            <div class="soft-card muted">
                <div class="pill">Segera Hadir</div>
                <div class="ic">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="24" cy="24" r="17"/>
                        <path d="M24 14v10l7 5"/>
                    </svg>
                </div>
                <b class="title">Presensi</b>
                <span class="sub">Manajemen Kehadiran</span>
            </div>
            <div class="soft-card muted">
                <div class="pill">Segera Hadir</div>
                <div class="ic">
                    <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 6h18l6 6v30a2 2 0 0 1-2 2H12a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2Z"/>
                        <path d="M30 6v6h6"/>
                        <path d="M16 24h16M16 31h16M16 17h8"/>
                    </svg>
                </div>
                <b class="title">Perpustakaan</b>
                <span class="sub">Perpustakaan Hybrid</span>
            </div>
        </div>
    </div>
</section>

<footer>
    {{ $AppCfg['footer_text'] ?? ('© '.date('Y').' '.$AppCfg['app_name'].' — '.$AppCfg['app_tagline']) }}
</footer>

</body>
</html>
