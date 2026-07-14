@extends('layouts.app')
@section('title', 'Kelola Soal: '.$tes->name)

@section('content')
<div x-data="tesSoalPreview()">
<x-page-header :title="$tes->name" :subtitle="'Kelola soal dalam tes ini'">
    <x-slot:action>
        <a href="{{ route('tes.edit', $tes) }}" class="btn-secondary"><x-icon name="edit" class="w-4 h-4"/> Edit Registrasi</a>

        {{-- Dropdown export — soal yang sudah di-attach ke ujian ini saja --}}
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" class="btn-secondary" type="button"
                    @if($tes->questions->isEmpty()) disabled title="Tambahkan minimal 1 soal dulu" @endif>
                <x-icon name="chart" class="w-4 h-4"/> Export Soal
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                 class="absolute right-0 mt-2 w-64 card overflow-hidden z-30">
                <div class="px-4 py-2 text-[10px] text-ink-500 bg-slate-50 border-b border-slate-100 uppercase tracking-wide">
                    {{ $tes->questions->count() }} soal di tes ini
                </div>
                <a href="{{ route('tes.export.word', $tes) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">
                    📝 Export ke Word (.docx)
                </a>
                <a href="{{ route('tes.export.pdf', $tes) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50">
                    📄 Export ke PDF (soal saja)
                </a>
                <a href="{{ route('tes.export.pdf', [$tes, 'with_answer' => 1]) }}" class="block px-4 py-2.5 text-sm hover:bg-slate-50 border-t border-slate-100">
                    📄 Export PDF + Kunci Jawaban
                </a>
            </div>
        </div>
    </x-slot:action>
</x-page-header>

<div class="grid lg:grid-cols-2 gap-6">
    {{-- Soal terpasang --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="text-base font-semibold">Soal Dalam Ujian ({{ $tes->questions->count() }})</h3>
                <p class="text-xs text-ink-500">Total Nilai: {{ number_format($tes->total_marks, 2) }}</p>
            </div>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($tes->questions as $idx => $qq)
                <li class="px-6 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-ink-500">#{{ $idx + 1 }} &middot; {{ $qq->marks }} poin</div>
                        <div class="font-semibold text-ink-900">{{ $qq->question->title }}</div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="openPreview({{ $qq->question_id }})"
                                class="btn-ghost p-2 text-brand-600" title="Preview soal">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                        <form method="POST" action="{{ route('tes.detach-question', [$tes, $qq]) }}" onsubmit="return confirm('Hapus dari tes?')">
                            @csrf @method('DELETE')
                            <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-6 py-6 text-center text-ink-500">Belum ada soal. Tambahkan dari panel kanan.</li>
            @endforelse
        </ul>
    </div>

    {{-- Bank soal tersedia --}}
    <div class="card">
        <div class="card-header">
            <h3 class="text-base font-semibold">Bank Soal Tersedia</h3>
            @unless($tes->mata_pelajaran_id)
                <p class="text-xs text-brand-600 mt-0.5">🌐 Ujian Umum — menampilkan soal dari SEMUA mata pelajaran.</p>
            @endunless
        </div>
        <form method="GET" class="px-6 pt-4 flex gap-2">
            <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari soal di bank...">
            <button class="btn-secondary"><x-icon name="search" class="w-4 h-4"/></button>
        </form>
        <ul class="divide-y divide-slate-100 mt-2">
            @forelse($available as $q)
                <li class="px-6 py-3 flex items-start justify-between gap-3">
                    <div>
                        <div class="text-xs text-ink-500">{{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}</div>
                        <div class="font-semibold text-ink-900">{{ $q->title }}</div>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="openPreview({{ $q->id }})"
                                class="btn-ghost p-2 text-brand-600" title="Preview soal sebelum ditambahkan">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                            </svg>
                        </button>
                        <form method="POST" action="{{ route('tes.attach-question', $tes) }}" class="flex gap-1 items-center">
                            @csrf
                            <input type="hidden" name="question_id" value="{{ $q->id }}">
                            <input type="number" step="0.1" name="marks" value="1" class="input w-20 text-center" title="Nilai">
                            <button class="btn-primary text-xs px-3 py-1.5"><x-icon name="plus" class="w-4 h-4"/></button>
                        </form>
                    </div>
                </li>
            @empty
                <li class="px-6 py-6 text-center text-ink-500">Tidak ada soal tersedia.</li>
            @endforelse
        </ul>
        <div class="px-6 pb-4">{{ $available->links() }}</div>
    </div>
</div>

{{-- MODAL PREVIEW SOAL (pakai endpoint bank-soal.preview yang sama dgn halaman Bank Soal) --}}
<div x-show="modalOpen" x-cloak
     class="fixed inset-0 z-50 grid place-items-center bg-slate-900/60 p-4"
     @keydown.escape.window="modalOpen = false">
    <div @click.outside="modalOpen = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between p-4 border-b border-slate-100">
            <h3 class="font-bold text-ink-900 flex items-center gap-2">
                <span><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                    <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                </svg></span> Preview Soal
            </h3>
            <button @click="modalOpen = false" class="btn-ghost p-2 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">
            <div x-show="loading" class="text-center py-8 text-ink-500">Memuat preview...</div>
            <div x-show="!loading" x-html="content"></div>
        </div>
    </div>
</div>

<script>
function tesSoalPreview() {
    return {
        modalOpen: false,
        loading: false,
        content: '',

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
            }
        },
    };
}
window.tesSoalPreview = tesSoalPreview;
</script>
</div>
@endsection
