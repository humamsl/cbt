@php
    $user = auth()->user();
    $role = $user->user_type ?? 'admin';
    $showCbt = in_array($role, ['admin', 'guru'], true);
@endphp

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 border-r border-black/5 transform md:translate-x-0 transition-transform"
    style="background-color: #062275;"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    @click="if (window.innerWidth < 768 && $event.target.closest('a[href]')) sidebarOpen = false">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-white/15">
        @if($AppCfg['logo'])
            <img src="{{ Storage::url($AppCfg['logo']) }}" alt="" class="w-10 h-10 object-contain rounded-lg bg-white/90 p-0.5 shadow-soft">
        @else
            <div class="w-10 h-10 rounded-xl bg-white/20 grid place-items-center text-white font-bold shadow-soft ring-2 ring-white/40">
                {{ mb_substr($AppCfg['app_name'], 0, 1) }}
            </div>
        @endif
        <div class="min-w-0">
            <div class="text-sm font-bold text-white leading-tight truncate">{{ $AppCfg['app_name'] }}</div>
            <div class="text-[11px] text-white leading-tight truncate">{{ $AppCfg['app_tagline'] }}</div>
        </div>
    </div>

    <nav class="px-3 py-4 overflow-y-auto h-[calc(100vh-4rem)]">
        @if($role === 'admin')
            <a href="{{ config('services.landing.app_url') }}"
               class="flex items-center justify-center gap-2 mx-1 mb-3 px-3 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-xs font-semibold transition">
                <x-icon name="grid" class="w-3.5 h-3.5"/> Ganti Aplikasi
            </a>
        @endif

        <div class="sidebar-section">Beranda</div>
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-icon name="home"/> Dashboard
        </a>

        {{-- CBT: admin & guru --}}
        @if($showCbt)
            <div class="sidebar-section">CBT</div>
            <a href="{{ route('topik.index') }}" class="sidebar-link {{ request()->routeIs('topik.*') ? 'active' : '' }}">
                <x-icon name="bookmark"/> Topik
            </a>
            <a href="{{ route('bank-soal.index') }}" class="sidebar-link {{ request()->routeIs('bank-soal.*') ? 'active' : '' }}">
                <x-icon name="document"/> Bank Soal
            </a>
            <a href="{{ route('tes.index') }}" class="sidebar-link {{ request()->routeIs('tes.*') ? 'active' : '' }}">
                <x-icon name="clipboard"/> Registrasi Ujian
            </a>
            <a href="{{ route('hasil.index') }}" class="sidebar-link {{ request()->routeIs('hasil.*') ? 'active' : '' }}">
                <x-icon name="chart"/> Hasil & Laporan
            </a>
            <a href="{{ route('monitoring.index') }}" class="sidebar-link {{ request()->routeIs('monitoring.*') ? 'active' : '' }}">
                <x-icon name="clock"/> Monitoring Ujian
            </a>
        @endif

        @if($role === 'siswa')
            <div class="sidebar-section">Ujian Saya</div>
            <a href="{{ route('siswa.ujian.index') }}" class="sidebar-link {{ request()->routeIs('siswa.ujian.*') ? 'active' : '' }}">
                <x-icon name="clipboard"/> Daftar Ujian
            </a>
            <a href="{{ route('siswa.riwayat') }}" class="sidebar-link {{ request()->routeIs('siswa.riwayat') ? 'active' : '' }}">
                <x-icon name="chart"/> Riwayat & Nilai
            </a>
        @endif

        @if($role === 'admin')
            <div class="sidebar-section">Administrasi</div>

            <a href="{{ route('log-login.index') }}" class="sidebar-link {{ request()->routeIs('log-login.*') ? 'active' : '' }}">
                <x-icon name="key"/> Log Login
            </a>
            <a href="{{ route('token-sesi.index') }}" class="sidebar-link {{ request()->routeIs('token-sesi.*') ? 'active' : '' }}">
                <x-icon name="key"/> Token Sesi
            </a>
        @endif

    </nav>
</aside>

<div x-show="sidebarOpen" x-cloak
     @click="sidebarOpen = false"
     class="fixed inset-0 z-20 bg-ink-900/50 md:hidden"></div>
