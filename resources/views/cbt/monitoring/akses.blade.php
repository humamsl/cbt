@extends('layouts.app')
@section('title', 'Setting Akses Monitoring Ujian')
@section('breadcrumb', 'Admin / Monitoring Ujian / Setting Akses')

@section('content')
<div class="max-w-3xl space-y-5">

    {{-- Form tambah akses --}}
    <div class="card">
        <div class="card-header">
            <h2 class="font-bold text-ink-900">Setting Akses Monitoring Ujian</h2>
            <a href="{{ route('monitoring.index') }}" class="btn-ghost text-xs">&larr; Kembali ke Monitoring</a>
        </div>
        <form method="POST" action="{{ route('monitoring.akses.store') }}" class="card-pad space-y-4">
            @csrf
            <div class="rounded-xl border border-slate-200 p-4 space-y-4">
                <div>
                    <label class="label">Petugas Monitoring Ujian <span class="text-rose-500">*</span></label>
                    <select name="guru_id" class="select" required>
                        <option value="">Pilih Petugas</option>
                        @foreach($gurus as $g)
                            <option value="{{ $g->id }}" @selected(old('guru_id') == $g->id)>
                                {{ $g->nama_ptk }} — {{ $g->nip }}
                            </option>
                        @endforeach
                    </select>
                    @error('guru_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label">Pilih Kelas yang hendak di monitoring <span class="text-rose-500">*</span></label>
                    <div class="max-h-56 overflow-y-auto rounded-xl border border-slate-200 p-3 grid grid-cols-2 sm:grid-cols-3 gap-1">
                        @forelse($rombels as $r)
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-sm hover:bg-brand-50 cursor-pointer">
                                <input type="checkbox" name="rombel_ids[]" value="{{ $r->id }}"
                                       @checked(in_array($r->id, (array) old('rombel_ids', [])))
                                       class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                <span>{{ $r->nama_rombel }}</span>
                            </label>
                        @empty
                            <div class="col-span-full text-center text-xs text-ink-500 py-3">Belum ada rombel di tahun ajaran aktif.</div>
                        @endforelse
                    </div>
                    <p class="mt-1 text-xs text-ink-500">Bisa memilih lebih dari satu kelas. Guru yang ditunjuk akan bisa memonitoring semua ujian yang menarget kelas terpilih.</p>
                    @error('rombel_ids')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end">
                <button class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Tambah</button>
            </div>
        </form>
    </div>

    {{-- Daftar akses yang sudah diberikan --}}
    <div class="card">
        <div class="card-header">
            <h3 class="font-bold text-ink-900">Petugas Terdaftar</h3>
            <span class="badge-brand">{{ $aksesPerGuru->count() }} petugas</span>
        </div>
        <div class="table-wrap">
        <table class="table-modern">
            <thead><tr><th>Petugas</th><th>Kelas yang Dimonitoring</th></tr></thead>
            <tbody>
            @forelse($aksesPerGuru as $rows)
                <tr>
                    <td class="align-top">
                        <div class="font-semibold text-ink-900">{{ optional($rows->first()->guru)->nama_ptk ?? 'Guru tidak ditemukan' }}</div>
                        <div class="text-xs text-ink-500">{{ optional($rows->first()->guru)->nip }}</div>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($rows as $a)
                                <span class="badge-muted inline-flex items-center gap-1">
                                    {{ optional($a->rombel)->nama_rombel ?? 'Rombel #'.$a->rombongan_belajar_id }}
                                    <form method="POST" action="{{ route('monitoring.akses.destroy', $a) }}" class="inline"
                                          onsubmit="return confirm('Hapus akses kelas ini?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-rose-500 hover:text-rose-700 font-bold leading-none" title="Hapus akses kelas ini">&times;</button>
                                    </form>
                                </span>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="2" class="text-center py-8 text-ink-500">Belum ada petugas yang diberi akses monitoring.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
