@php
    $user = auth()->user();
    $role = $user->user_type ?? 'admin';
    $name = $user->name ?? ($user->nama_siswa ?? ($user->nama_ptk ?? $user->email));
    $roleBadge = [
        'admin' => 'bg-rose-50 text-rose-700 ring-rose-200',
        'guru'  => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'siswa' => 'bg-sky-50 text-sky-700 ring-sky-200',
    ][$role] ?? 'bg-slate-100 text-slate-600 ring-slate-200';
@endphp
<header class="sticky top-0 z-20 bg-white/95 backdrop-blur-md border-b border-slate-100/10">
    <div class="flex items-center justify-between h-16 px-4 md:px-8 gap-2">
        <div class="flex items-center gap-2 md:gap-3 min-w-0 flex-1">
            <button @click="sidebarOpen = !sidebarOpen" class="md:hidden btn-ghost p-2 shrink-0">
                <x-icon name="menu"/>
            </button>
            <div class="min-w-0">
                <div class="text-xs text-ink-500 flex items-center gap-1.5 truncate">
                    <span class="w-1 h-1 rounded-full bg-brand-500 shrink-0"></span>
                    <span class="truncate">@yield('breadcrumb', 'Beranda')</span>
                </div>
                <h1 class="text-base md:text-lg font-semibold text-ink-900 leading-tight truncate">@yield('title', 'Dashboard')</h1>
            </div>
        </div>
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            @if($th = \App\Models\TahunAjaran::aktif())
                <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-brand-50 text-brand-700 ring-1 ring-brand-200">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                    TA {{ $th->nama_tahun_ajaran }} &middot; {{ $th->semester }}
                </span>
            @endif
            <div x-data="{ open: false }" class="relative">
                <button @click="open=!open" class="flex items-center gap-2.5 group">
                    <img src="{{ $user->profile_photo_url ?? 'https://ui-avatars.com/api/?name='.urlencode($name) }}"
                         alt="" class="w-9 h-9 rounded-full ring-2 ring-white shadow-soft object-cover group-hover:ring-brand-200 transition">
                    <div class="hidden sm:block min-w-0 text-left">
                        <div class="text-sm font-semibold text-ink-900 truncate max-w-[160px]">{{ $name }}</div>
                        <div class="text-[10px] uppercase tracking-wider font-bold inline-flex px-1.5 py-0.5 rounded ring-1 {{ $roleBadge }}">{{ $role }}</div>
                    </div>
                </button>
                <div x-show="open" @click.outside="open=false" x-cloak x-transition
                     class="absolute right-0 mt-2 w-56 card overflow-hidden z-30">
                    <a href="{{ route('profil.index') }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50 flex items-center gap-2">
                        <x-icon name="user" class="w-4 h-4"/> Profil saya
                    </a>
                    @if($role === 'admin')
                        <a href="{{ route('setting.index') }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50 flex items-center gap-2">
                            <x-icon name="settings" class="w-4 h-4"/> Pengaturan
                        </a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="border-t border-slate-100">
                        @csrf
                        <button class="w-full text-left px-4 py-2.5 text-sm text-rose-600 hover:bg-rose-50 flex items-center gap-2">
                            <x-icon name="logout" class="w-4 h-4"/> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
