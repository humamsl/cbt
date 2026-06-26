@extends('layouts.app')
@section('title', 'Ujian Terblokir')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card overflow-hidden">
        <div class="p-8 bg-gradient-to-br from-rose-600 to-rose-800 text-white text-center">
            <div class="mx-auto w-20 h-20 rounded-full bg-white/15 grid place-items-center mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-11 h-11">
                    <rect x="3" y="11" width="18" height="11" rx="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <h2 class="text-2xl font-bold">Ujian Diblokir</h2>
            <p class="text-rose-100 mt-2 text-sm">{{ $quiz->name }}</p>
        </div>
        <div class="p-8 space-y-4">
            <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-800">
                <div class="font-semibold mb-1">Alasan blokir:</div>
                <div>{{ $attempt->blocked_reason ?? 'Pelanggaran aturan ujian terlalu sering' }}</div>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm">
                <div class="card card-pad text-center">
                    <div class="text-xs text-ink-500">Pelanggaran</div>
                    <div class="text-2xl font-bold text-rose-600 mt-1">{{ $attempt->violation_count }}</div>
                </div>
                <div class="card card-pad text-center">
                    <div class="text-xs text-ink-500">Diblokir pada</div>
                    <div class="text-sm font-semibold mt-2">{{ optional($attempt->blocked_at)->format('d M Y H:i') }}</div>
                </div>
            </div>

            <p class="text-sm text-ink-600">
                Akses ujian Anda telah dikunci. Hubungi <strong>pengawas atau administrator sekolah</strong> untuk
                membuka kembali akses ujian ini.
            </p>

            <div class="flex justify-end pt-3 border-t border-slate-100">
                <a href="{{ route('siswa.ujian.index') }}" class="btn-secondary">Kembali ke Daftar Ujian</a>
            </div>
        </div>
    </div>
</div>
@endsection
