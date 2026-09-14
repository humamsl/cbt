@extends('layouts.app')
@section('title', 'Daftar Ujian')

@section('content')
<x-page-header title="Daftar Ujian Tersedia" subtitle="Pilih ujian untuk dikerjakan"/>
{{-- Flash error/success (mis. "waktu ujian sudah berakhir" dari start()) sudah
     ditampilkan otomatis oleh layouts/app.blade.php di atas @yield('content'),
     tidak perlu diulang di sini. --}}

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($quizzes as $q)
        @php($st = $statusUjian[$q->id] ?? null)
        <div class="card card-pad flex flex-col">
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="badge-info">{{ optional($q->mapel)->nama_mapel ?? 'Umum' }}</span>
                <span class="{{ $q->status_badge }}">{{ ucfirst($q->status) }}</span>
                @if($q->require_session_token)
                    <span class="badge-warning"><x-icon name="key" class="inline w-3.5 h-3.5"/> Perlu Token</span>
                @endif
            </div>
            <div class="text-lg font-bold text-ink-900">{{ $q->name }}</div>
            <div class="text-sm text-ink-500 mt-1 flex-1">{{ Str::limit($q->description, 100) }}</div>
            <div class="mt-3 flex items-center justify-between text-xs text-ink-500 border-t border-slate-100 pt-3">
                <span><x-icon name="clock" class="inline w-3.5 h-3.5"/> {{ $q->duration }} menit</span>
                <span>{{ $q->questions_count }} soal</span>
            </div>
            @if($st && $st['attempt_blokir'])
                <a href="{{ route('siswa.ujian.blocked', [$q, $st['attempt_blokir']]) }}"
                   class="btn-secondary w-full justify-center mt-4 opacity-75">
                    <x-icon name="key" class="w-4 h-4"/> Ujian Terkunci (Diblokir)
                </a>
                <p class="text-xs text-rose-600 text-center mt-1">Diblokir karena pelanggaran. Hubungi admin/guru.</p>
            @elseif($st && $st['attempt_terbaru_selesai'])
                <a href="{{ route('siswa.ujian.result', [$q, $st['attempt_terbaru_selesai']]) }}"
                   class="btn-secondary w-full justify-center mt-4">
                    <x-icon name="check" class="w-4 h-4"/> Selesai Dikerjakan
                </a>
                <p class="text-xs text-emerald-600 text-center mt-1">Anda sudah menyelesaikan ujian ini.</p>
            @elseif($q->belum_dimulai)
                <button type="button" disabled
                        class="btn-secondary w-full justify-center mt-4 opacity-60 cursor-not-allowed">
                    <x-icon name="clock" class="w-4 h-4"/> Belum Dimulai
                </button>
                <p class="text-xs text-ink-500 text-center mt-1">
                    Ujian dibuka {{ $q->valid_from->format('d M Y \p\u\k\u\l H:i') }}
                </p>
            @else
                @php($sedang = $st['attempt_sedang'] ?? null)
                <form method="POST" action="{{ route('siswa.ujian.start', $q) }}" class="mt-4 space-y-2">
                    @csrf
                    @if($q->require_session_token)
                        <input type="text" name="token" placeholder="Masukkan Token Sesi" required maxlength="12" autocomplete="off"
                               class="input w-full uppercase tracking-widest text-center font-mono">
                        @error('token', 'quiz'.$q->id)<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    @endif
                    {{-- Sebelumnya selalu "Mulai Ujian" walau attempt lama sudah ada &
                         belum selesai (start() tetap melanjutkan attempt itu, tapi
                         labelnya menyesatkan seolah mau mulai baru -- siswa yang baru
                         saja ke-logout paksa jadi ragu apakah klik ini akan menghapus
                         jawabannya). --}}
                    <button class="btn-primary w-full">
                        {{ $sedang ? 'Lanjutkan Ujian' : 'Mulai Ujian' }} <x-icon name="arrow-right" class="w-4 h-4"/>
                    </button>
                </form>
                @if($sedang)
                    <p class="text-xs text-brand-600 text-center mt-1">Anda punya jawaban tersimpan dari sesi sebelumnya — lanjutkan dari situ.</p>
                @endif
            @endif
        </div>
    @empty
        <div class="col-span-full card card-pad text-center text-ink-500">Belum ada ujian yang dapat dikerjakan.</div>
    @endforelse
</div>
<div class="mt-4">{{ $quizzes->links() }}</div>
@endsection
