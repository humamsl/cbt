@php
    $user = auth()->user();
    $role = $user->user_type ?? 'admin';
@endphp

<aside
    class="fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border-slate-100 transform md:translate-x-0 transition-transform"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    @click="if (window.innerWidth < 768 && $event.target.closest('a[href]')) sidebarOpen = false">
    <div class="flex items-center gap-3 px-5 h-16 border-b border-slate-100 bg-gradient-to-r from-white to-brand-50/30">
        @if($AppCfg['logo'])
            <img src="{{ Storage::url($AppCfg['logo']) }}" alt="" class="w-10 h-10 object-contain rounded-lg shadow-soft">
        @else
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 grid place-items-center text-white font-bold shadow-soft ring-2 ring-white">
                {{ mb_substr($AppCfg['app_name'], 0, 1) }}
            </div>
        @endif
        <div class="min-w-0">
            <div class="text-sm font-bold text-ink-900 leading-tight truncate">{{ $AppCfg['app_name'] }}</div>
            <div class="text-[11px] text-ink-500 leading-tight truncate">{{ $AppCfg['app_tagline'] }}</div>
        </div>
    </div>

    <nav class="px-3 py-4 overflow-y-auto h-[calc(100vh-4rem)]">
        <div class="sidebar-section">Beranda</div>
        <a href="{{ route('dashboard') }}"
           class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <x-icon name="home"/> Dashboard
        </a>

        {{-- Data Center: HANYA admin --}}
        @if($role === 'admin')
            <div class="sidebar-section">Data Center</div>
            <a href="{{ route('tahun-ajaran.index') }}" class="sidebar-link {{ request()->routeIs('tahun-ajaran.*') ? 'active' : '' }}">
                <x-icon name="calendar"/> Tahun Ajaran
            </a>
            <a href="{{ route('jurusan.index') }}" class="sidebar-link {{ request()->routeIs('jurusan.*') ? 'active' : '' }}">
                <x-icon name="layers"/> Jurusan
            </a>
            <a href="{{ route('mapel.index') }}" class="sidebar-link {{ request()->routeIs('mapel.*') ? 'active' : '' }}">
                <x-icon name="book"/> Mata Pelajaran
            </a>
            <a href="{{ route('tingkat-kelas.index') }}" class="sidebar-link {{ request()->routeIs('tingkat-kelas.*') ? 'active' : '' }}">
                <x-icon name="layers"/> Tingkat Kelas
            </a>
            <a href="{{ route('rombel.index') }}" class="sidebar-link {{ request()->routeIs('rombel.*') ? 'active' : '' }}">
                <x-icon name="grid"/> Rombongan Belajar
            </a>
            <a href="{{ route('guru.index') }}" class="sidebar-link {{ request()->routeIs('guru.*') ? 'active' : '' }}">
                <x-icon name="user-tie"/> Data Guru
            </a>
            <a href="{{ route('guru-mapel.index') }}" class="sidebar-link {{ request()->routeIs('guru-mapel.*') ? 'active' : '' }}">
                <x-icon name="bookmark"/> Guru Mapel
            </a>
            <a href="{{ route('siswa.index') }}" class="sidebar-link {{ request()->routeIs('siswa.*') ? 'active' : '' }}">
                <x-icon name="users"/> Data Siswa
            </a>
        @endif

        {{-- CBT: admin & guru --}}
        @if($role === 'admin' || $role === 'guru')
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
