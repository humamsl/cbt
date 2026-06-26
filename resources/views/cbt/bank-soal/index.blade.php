@extends('layouts.app')
@section('title', 'Bank Soal')
@section('breadcrumb', 'CBT / Bank Soal')

@section('content')
<div x-data="bankSoalPage()">
<x-page-header title="Bank Soal" subtitle="Kumpulan butir soal lintas mapel & jenis">
    <x-slot:action>
        <a href="{{ route('bank-soal.preview.mapel', ['mapel' => request('mapel')]) }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Preview Mapel
        </a>
        <a href="{{ route('bank-soal.import.form') }}" class="btn-secondary">
            <x-icon name="document" class="w-4 h-4"/> Import
        </a>
        <a href="{{ route('bank-soal.create') }}" class="btn-primary"><x-icon name="plus" class="w-4 h-4"/> Buat Soal</a>
    </x-slot:action>
</x-page-header>

<form class="card card-pad mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" class="input flex-1 min-w-[200px]" placeholder="Cari judul / isi soal...">
    <select name="mapel" class="select w-48">
        <option value="">Semua mapel</option>
        @foreach($mapelList as $m)
            <option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>
        @endforeach
    </select>
    <select name="jenis" class="select w-52">
        <option value="">Semua jenis</option>
        @foreach($types as $t)
            <option value="{{ $t->slug }}" @selected(request('jenis')==$t->slug)>{{ $t->question_type }}</option>
        @endforeach
    </select>
    <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
</form>

<div class="grid gap-3">
    @forelse($items as $q)
        <div class="card card-pad flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span class="badge-info">{{ optional($q->type)->question_type }}</span>
                    <span class="badge-muted">{{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}</span>
                    <span class="badge-{{ ['mudah'=>'success','sedang'=>'warning','sulit'=>'danger'][$q->tingkat_kesulitan] ?? 'muted' }}">
                        {{ ucfirst($q->tingkat_kesulitan) }}
                    </span>
                </div>
                <div class="font-semibold text-ink-900">{{ $q->title }}</div>
                <div class="text-sm text-ink-500 mt-1 line-clamp-2">{{ Str::limit(strip_tags($q->question), 200) }}</div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button type="button" @click="openPreview({{ $q->id }})"
                        class="btn-ghost p-2 text-brand-600" title="Preview soal">
                    👁️‍🗨️
                </button>
                <a href="{{ route('bank-soal.edit', $q) }}" class="btn-ghost p-2"><x-icon name="edit"/></a>
                <form method="POST" action="{{ route('bank-soal.destroy', $q) }}" onsubmit="return confirm('Hapus soal?')">
                    @csrf @method('DELETE')<button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                </form>
            </div>
        </div>
    @empty
        <div class="card card-pad text-center text-ink-500">Belum ada soal di bank.</div>
    @endforelse
</div>
<div class="mt-4">{{ $items->links() }}</div>

{{-- MODAL PREVIEW SOAL --}}
<div x-show="modalOpen" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 p-4"
     @keydown.escape.window="modalOpen = false">
    <div @click.outside="modalOpen = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-slate-100">
            <h3 class="font-bold text-ink-900 flex items-center gap-2">
                <span>👁️‍🗨️</span> Preview Soal
            </h3>
            <button @click="modalOpen = false" class="btn-ghost p-2 text-xl leading-none">×</button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">
            <div x-show="loading" class="text-center py-8 text-ink-500">Memuat preview...</div>
            <div x-show="!loading" x-html="content"></div>
        </div>
    </div>
</div>

<script>
function bankSoalPage() {
    return {
        modalOpen: false,
        loading: false,
        content: '',

        async openPreview(id) {
            this.modalOpen = true;
            this.loading = true;
            this.content = '';
            try {
                const res = await fetch(`/bank-soal/${id}/preview`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (! res.ok) throw new Error('Gagal memuat (' + res.status + ')');
                this.content = await res.text();
            } catch (e) {
                this.content = `<div class="text-rose-600 text-sm">Error: ${e.message}</div>`;
            } finally {
                this.loading = false;
            }
        },
    };
}
window.bankSoalPage = bankSoalPage;
</script>
</div>
@endsection
