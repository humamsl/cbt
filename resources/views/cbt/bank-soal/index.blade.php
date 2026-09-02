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

{{-- Grid kolom pecahan, BUKAN flex.
     Versi flex membuat kotak "Cari judul" satu-satunya yang menyerap
     penyempitan (flex-1 = flex-basis 0) sementara ketiga select memegang lebar
     tetap, dan kotak itu hanya tertolong oleh min-w-[200px] -- sekali kelas itu
     tidak ikut ter-build, kotaknya kolaps jadi selebar padding saja. Dengan
     col-span, tiap kontrol punya jatah pasti dan tidak bergantung pada urutan
     shrink maupun min-width.

     Susunan: HP 2 kolom -> lg 4 kolom (cari sebaris penuh, 4 kontrol di bawahnya)
     -> xl 12 kolom (semua muat satu baris; span 4/3/2/2/1). --}}
<form class="card card-pad mb-4 grid grid-cols-2 gap-2 lg:grid-cols-4 xl:grid-cols-12">
    <input name="q" value="{{ request('q') }}" class="input col-span-2 lg:col-span-4" placeholder="Cari judul / isi soal...">
    {{-- Mapel auto-submit: daftar tingkat di sebelahnya mengikuti mapel terpilih --}}
    <select name="mapel" class="select col-span-2 sm:col-span-1 xl:col-span-3" onchange="this.form.submit()">
        <option value="">Semua mapel</option>
        @foreach($mapelList as $m)
            <option value="{{ $m->id }}" @selected(request('mapel')==$m->id)>{{ $m->nama_mapel }}</option>
        @endforeach
    </select>
    <select name="jenis" class="select xl:col-span-2">
        <option value="">Semua jenis</option>
        @foreach($types as $t)
            <option value="{{ $t->slug }}" @selected(request('jenis')==$t->slug)>{{ $t->question_type }}</option>
        @endforeach
    </select>
    {{-- RULE guru: filter kelas baru aktif setelah pilih mapel — daftar
         tingkatnya pun hanya tingkat di mana dia mengajar mapel tsb --}}
    @if($tingkatButuhMapel ?? false)
        <select class="select xl:col-span-2" disabled title="Pilih mapel dulu untuk memfilter kelas">
            <option>Pilih mapel dulu…</option>
        </select>
    @else
        <select name="tingkat" class="select xl:col-span-2">
            <option value="">Semua kelas</option>
            @foreach($tingkatList as $nomor => $nama)
                <option value="{{ $nomor }}" @selected(request('tingkat') == $nomor)>{{ $nama }}</option>
            @endforeach
        </select>
    @endif
    <button class="btn-secondary col-span-2 sm:col-span-1 xl:col-span-1"><x-icon name="search" class="w-4 h-4"/> <span class="xl:hidden">Cari</span></button>
</form>

@if($items->count())
<div class="card card-pad mb-3 flex items-center justify-between gap-3 flex-wrap">
    <label class="flex items-center gap-2 text-sm text-ink-600 select-none">
        <input type="checkbox" class="rounded text-brand-600 focus:ring-brand-500" :checked="allOnPageSelected()" @change="toggleAllOnPage($event.target.checked)">
        Pilih semua di halaman ini
    </label>
    <div x-show="selected.length" x-cloak class="flex items-center gap-3">
        <span class="text-sm text-ink-500"><span x-text="selected.length"></span> soal dipilih</span>
        <button type="button" @click="bulkDelete()" class="btn-secondary text-rose-600">
            <x-icon name="trash" class="w-4 h-4"/> Hapus Terpilih
        </button>
    </div>
</div>
@endif

<div class="grid gap-3">
    @forelse($items as $q)
        <div class="card card-pad flex items-start justify-between gap-4">
            <input type="checkbox" class="rounded text-brand-600 focus:ring-brand-500 mt-1 shrink-0" value="{{ $q->id }}"
                   :checked="selected.includes({{ $q->id }})" @change="toggleSelect({{ $q->id }}, $event.target.checked)">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                    <span class="badge-info">{{ optional($q->type)->question_type }}</span>
                    <span class="badge-muted">{{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}</span>
                    <span class="badge-{{ ['mudah'=>'success','sedang'=>'warning','sulit'=>'danger'][$q->tingkat_kesulitan] ?? 'muted' }}">
                        {{ ucfirst($q->tingkat_kesulitan) }}
                    </span>
                </div>
                <div class="font-semibold text-ink-900">{{ $q->title }}</div>
                <div class="text-sm text-ink-500 mt-1 line-clamp-2">{{ Str::limit(html_entity_decode(strip_tags($q->question)), 200) }}</div>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button type="button" @click="openPreview({{ $q->id }})"
                        class="btn-ghost p-2 text-brand-600" title="Preview soal">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
</svg>
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

{{-- Form tersembunyi untuk submit hapus massal --}}
<form method="POST" action="{{ route('bank-soal.bulk-destroy') }}" x-ref="bulkDeleteForm">
    @csrf
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="ids[]" :value="id">
    </template>
</form>

{{-- MODAL PREVIEW SOAL --}}
<div x-show="modalOpen" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 p-4"
     @keydown.escape.window="modalOpen = false">
    <div @click.outside="modalOpen = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-slate-100">
            <h3 class="font-bold text-ink-900 flex items-center gap-2">
                <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-eye" viewBox="0 0 16 16">
  <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
  <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
</svg></span> Preview Soal
            </h3>
            <button @click="modalOpen = false" class="btn-ghost p-2 text-xl leading-none">×</button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">
            <div x-show="loading" class="text-center py-8 text-ink-500">Memuat preview...</div>
            <div x-show="!loading" x-html="content" x-ref="previewBody"></div>
        </div>
    </div>
</div>

<script>
function bankSoalPage() {
    return {
        modalOpen: false,
        loading: false,
        content: '',
        selected: [],
        idsOnPage: [@foreach($items as $q){{ $q->id }},@endforeach],

        toggleSelect(id, checked) {
            if (checked) {
                if (! this.selected.includes(id)) this.selected.push(id);
            } else {
                this.selected = this.selected.filter(x => x !== id);
            }
        },

        allOnPageSelected() {
            return this.idsOnPage.length > 0 && this.idsOnPage.every(id => this.selected.includes(id));
        },

        toggleAllOnPage(checked) {
            if (checked) {
                this.idsOnPage.forEach(id => { if (! this.selected.includes(id)) this.selected.push(id); });
            } else {
                this.selected = this.selected.filter(id => ! this.idsOnPage.includes(id));
            }
        },

        bulkDelete() {
            if (! this.selected.length) return;
            if (! confirm(`Hapus ${this.selected.length} soal terpilih?`)) return;
            this.$refs.bulkDeleteForm.submit();
        },

        async openPreview(id) {
            this.modalOpen = true;
            this.loading = true;
            this.content = '';
            try {
                // Pakai route() supaya URL menyertakan base path aplikasi (mis. /cbt)
                // — fetch hardcoded "/bank-soal/..." akan 404 saat app di subfolder.
                const url = `{{ route('bank-soal.preview', ['bankSoal' => '__ID__']) }}`.replace('__ID__', id);
                const res = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (! res.ok) throw new Error('Gagal memuat (' + res.status + ')');
                this.content = await res.text();
            } catch (e) {
                this.content = `<div class="text-rose-600 text-sm">Error: ${e.message}</div>`;
            } finally {
                this.loading = false;
                // Konten datang via AJAX → render ulang rumus LaTeX-nya.
                this.$nextTick(() => window.renderSoalMath?.(this.$refs.previewBody));
            }
        },
    };
}
window.bankSoalPage = bankSoalPage;
</script>
</div>
@endsection
