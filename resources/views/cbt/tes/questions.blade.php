@extends('layouts.app')
@section('title', 'Kelola Soal: '.$tes->name)

@section('content')
<div x-data="tesSoalPage()">
{{-- Isi #tes-soal-panels diganti lewat AJAX setiap kali soal dipasang/dilepas
     atau filter diganti (lihat loadPanels() di bawah) — halaman tidak reload,
     jadi posisi gulir & filter topik/pencarian bank soal tetap. --}}
<div id="tes-soal-panels" x-ref="panels" @click="onNavClick($event)"
     :class="busy && 'opacity-60 pointer-events-none'" class="transition-opacity">
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
    <div class="card" id="panel-soal-terpasang">
        <div class="card-header">
            <div>
                <h3 class="text-base font-semibold">Soal Dalam Ujian ({{ $tes->questions->count() }})</h3>
                <p class="text-xs text-ink-500">Total Nilai: {{ number_format($tes->total_marks, 2) }}</p>
            </div>
        </div>
        <ul class="divide-y divide-slate-100">
            @forelse($tes->questions as $idx => $qq)
                <li class="px-4 sm:px-6 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-ink-500">
                                #{{ $idx + 1 }} &middot; {{ $qq->marks }} poin
                                @if($qq->question?->topic)<span class="badge-muted text-[10px] ml-1">{{ $qq->question->topic->topic }}</span>@endif
                            </div>
                            <div class="font-semibold text-ink-900">
                                {{ $qq->question->title ?? '⚠ Soal tidak dapat dimuat (sudah terhapus dari bank soal)' }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            @if($qq->question)
                                <button type="button" @click="openPreview({{ $qq->question_id }})"
                                        class="btn-ghost p-2 text-brand-600" title="Preview soal">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M16 8s-3-5.5-8-5.5S0 8 0 8s3 5.5 8 5.5S16 8 16 8M1.173 8a13 13 0 0 1 1.66-2.043C4.12 4.668 5.88 3.5 8 3.5s3.879 1.168 5.168 2.457A13 13 0 0 1 14.828 8q-.086.13-.195.288c-.335.48-.83 1.12-1.465 1.755C11.879 11.332 10.119 12.5 8 12.5s-3.879-1.168-5.168-2.457A13 13 0 0 1 1.172 8z"/>
                                        <path d="M8 5.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5M4.5 8a3.5 3.5 0 1 1 7 0 3.5 3.5 0 0 1-7 0"/>
                                    </svg>
                                </button>
                            @endif
                            {{-- Hapus dari tes HARUS tetap berfungsi walau soalnya sudah
                                 terhapus dari bank soal -- ini satu-satunya cara memulihkan
                                 tes yang kena soal yatim (lihat catatan panjang di
                                 BankSoalController::assertSoalTidakSedangDipakai()). --}}
                            <form method="POST" action="{{ route('tes.detach-question', [$tes, $qq]) }}" @submit.prevent="submitSoal($el, 'Hapus dari tes?')">
                                @csrf @method('DELETE')
                                <button class="btn-ghost p-2 text-rose-600"><x-icon name="trash"/></button>
                            </form>
                        </div>
                    </div>
                    {{-- Isi pertanyaan + opsi/kunci jawaban ditampilkan langsung --}}
                    @if($qq->question)
                        <div class="soal-math prose prose-sm max-w-none mt-2 text-sm text-ink-800 border border-slate-200 rounded-lg p-3 bg-slate-50/50 [&_img]:max-w-full [&_img]:h-auto">
                            @if(filled($qq->question->question))
                                {!! \App\Support\SoalHtml::render($qq->question->question) !!}
                            @else
                                <span class="text-ink-500 italic">— pertanyaan belum diisi —</span>
                            @endif
                        </div>
                        @include('cbt.bank-soal._soal-opsi', ['q' => $qq->question])
                    @else
                        <p class="text-xs text-rose-600 mt-2">Soal ini sudah terhapus dari bank soal. Klik ikon tempat sampah untuk melepaskannya dari tes ini.</p>
                    @endif
                </li>
            @empty
                <li class="px-4 sm:px-6 py-6 text-center text-ink-500">Belum ada soal. Tambahkan dari panel kanan.</li>
            @endforelse
        </ul>
    </div>

    {{-- Bank soal tersedia --}}
    <div class="card scroll-mt-20" id="panel-bank-soal">
        <div class="card-header">
            <h3 class="text-base font-semibold">Bank Soal Tersedia</h3>
            @unless($tes->mata_pelajaran_id)
                <p class="text-xs text-brand-600 mt-0.5">🌐 Ujian Umum — menampilkan soal dari SEMUA mata pelajaran.</p>
            @endunless
        </div>
        @php
            $topikAktif = (string) request('topik');
            $labelTopikAktif = collect($topikOptions)->firstWhere('id', $topikAktif)['label'] ?? null;
        @endphp
        <form method="GET" class="px-4 sm:px-6 pt-4 space-y-2" @submit.prevent="filterSoal($el)">
            {{-- Filter topik: klik → ketik untuk mencari topik → pilih. --}}
            <div class="relative" x-data="topikFilter(@js($topikOptions), @js($topikAktif))" @click.outside="close()">
                <input type="hidden" name="topik" x-ref="topikInput" :value="selected" value="{{ $topikAktif }}">
                <button type="button" x-ref="topikButton" @click="toggle()"
                        @keydown.arrow-down.prevent="show()"
                        class="select pr-3 py-2.5 flex items-center justify-between gap-2 text-left">
                    <span class="min-w-0 truncate">
                        <span class="text-ink-500">Topik:</span>
                        <span class="font-semibold text-ink-900" x-text="current ? current.label : 'Semua topik'">{{ $labelTopikAktif ?? 'Semua topik' }}</span>
                        <span class="text-xs text-ink-500" x-show="current && current.info" x-text="current ? '· ' + current.info : ''"></span>
                    </span>
                    <svg class="w-4 h-4 shrink-0 text-ink-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                     class="absolute left-0 right-0 mt-1 card overflow-hidden z-30">
                    <div class="p-2 border-b border-slate-100">
                        <input type="text" x-ref="topikSearch" x-model="query" @input="active = 0"
                               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)"
                               @keydown.enter.prevent="pick(filtered[active])"
                               @keydown.escape.prevent="close(); $refs.topikButton.focus()"
                               class="input" placeholder="Ketik nama topik..." autocomplete="off">
                    </div>
                    <ul x-ref="topikList" class="max-h-72 overflow-y-auto py-1">
                        <template x-for="(t, i) in filtered" :key="t.id">
                            <li>
                                <button type="button" @click="pick(t)" @mouseenter="active = i"
                                        :data-active="i === active"
                                        :class="i === active ? 'bg-brand-50' : ''"
                                        class="w-full px-3 py-2 text-left text-sm flex items-center justify-between gap-2">
                                    <span class="min-w-0">
                                        <span class="block truncate" :class="t.id === selected ? 'font-semibold text-brand-700' : 'text-ink-800'"
                                              x-text="(t.id === selected ? '✓ ' : '') + t.label"></span>
                                        <span class="block text-[11px] text-ink-500 truncate" x-show="t.info" x-text="t.info"></span>
                                    </span>
                                    <span class="badge-muted text-[10px] shrink-0" x-show="t.jml !== null"
                                          x-text="t.jml + ' soal'" title="Jumlah soal yang belum dipasang"></span>
                                </button>
                            </li>
                        </template>
                        <li x-show="!filtered.length" class="px-3 py-3 text-sm text-center text-ink-500">Topik tidak ditemukan.</li>
                    </ul>
                </div>
            </div>
            <div class="flex gap-2">
                <input name="q" value="{{ request('q') }}" class="input" placeholder="Cari judul soal...">
                <button class="btn-secondary" title="Cari"><x-icon name="search" class="w-4 h-4"/></button>
            </div>
        </form>
        <div class="px-4 sm:px-6 pt-2 text-xs text-ink-500 flex flex-wrap items-center gap-x-2" data-nav>
            <span>{{ $available->total() }} soal ditemukan</span>
            @if(request()->filled('q') || $topikAktif !== '')
                <a href="{{ route('tes.questions', $tes) }}" class="text-brand-600 hover:underline">× Reset filter</a>
            @endif
        </div>
        <ul class="divide-y divide-slate-100 mt-2">
            @forelse($available as $q)
                <li class="px-4 sm:px-6 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-ink-500">
                                {{ optional($q->mapel)->nama_mapel ?? 'Tanpa mapel' }}
                                @if($q->topic)<span class="badge-muted text-[10px] ml-1">{{ $q->topic->topic }}</span>@endif
                            </div>
                            <div class="font-semibold text-ink-900">{{ $q->title }}</div>
                        </div>
                        <form method="POST" action="{{ route('tes.attach-question', $tes) }}" class="flex gap-1 items-center shrink-0"
                              @submit.prevent="submitSoal($el)">
                            @csrf
                            <input type="hidden" name="question_id" value="{{ $q->id }}">
                            <input type="number" step="0.1" name="marks" value="1" class="input w-20 text-center" title="Nilai">
                            <button class="btn-primary text-xs px-3 py-1.5" title="Tambahkan ke tes"><x-icon name="plus" class="w-4 h-4"/></button>
                        </form>
                    </div>
                    {{-- Isi pertanyaan + opsi/kunci jawaban langsung ditampilkan (menggantikan modal preview) --}}
                    <div class="soal-math prose prose-sm max-w-none mt-2 text-sm text-ink-800 border border-slate-200 rounded-lg p-3 bg-slate-50/50 [&_img]:max-w-full [&_img]:h-auto">
                        @if(filled($q->question))
                            {!! \App\Support\SoalHtml::render($q->question) !!}
                        @else
                            <span class="text-ink-500 italic">— pertanyaan belum diisi —</span>
                        @endif
                    </div>
                    @include('cbt.bank-soal._soal-opsi', ['q' => $q])
                </li>
            @empty
                <li class="px-4 sm:px-6 py-6 text-center text-ink-500">
                    {{ request()->filled('q') || $topikAktif !== '' ? 'Tidak ada soal yang cocok dengan filter.' : 'Tidak ada soal tersedia.' }}
                </li>
            @endforelse
        </ul>
        <div class="px-4 sm:px-6 pb-4" data-nav>{{ $available->links() }}</div>
    </div>
</div>
</div>{{-- /#tes-soal-panels --}}

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
            <div x-show="!loading" x-html="content" x-ref="previewBody"></div>
        </div>
    </div>
</div>

{{-- Notifikasi hasil pasang/lepas soal (AJAX — flash session tidak dipakai) --}}
<div x-show="toast.show" x-cloak x-transition.opacity class="toast"
     :class="toast.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800'">
    <span class="w-7 h-7 rounded-full text-white grid place-items-center font-bold shrink-0"
          :class="toast.ok ? 'bg-emerald-500' : 'bg-rose-500'" x-text="toast.ok ? '✓' : '✕'"></span>
    <div class="flex-1" x-text="toast.message"></div>
    <button type="button" @click="toast.show = false" class="text-current/60 hover:text-current text-lg leading-none">×</button>
</div>

<script>
function tesSoalPage() {
    return {
        modalOpen: false,
        loading: false,
        content: '',
        busy: false,
        toast: { show: false, ok: true, message: '' },
        toastTimer: null,

        init() {
            // Tombol Back/Forward browser setelah filter/halaman diganti via AJAX.
            window.addEventListener('popstate', () => this.loadPanels(location.href));
        },

        /**
         * Muat ulang halaman ini di belakang layar lalu tukar isi
         * #tes-soal-panels saja. `anchor` = kartu yang sedang dipakai user:
         * posisinya di layar dipertahankan, jadi di HP (kolom bertumpuk)
         * daftar bank soal tidak "loncat" saat daftar soal terpasang di
         * atasnya bertambah panjang.
         */
        async loadPanels(url, { push = false, anchor = null } = {}) {
            this.busy = true;
            try {
                const res = await fetch(url, { headers: { Accept: 'text/html' } });
                if (! res.ok) throw new Error('Gagal memuat daftar soal (' + res.status + ')');
                const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
                const fresh = doc.getElementById('tes-soal-panels');
                // Bukan halaman ini (mis. sesi habis → dialihkan ke login): pindah halaman biasa.
                if (! fresh) { window.location.href = res.url || url; return false; }

                const finalUrl = res.url || url;
                if (push) history.pushState(null, '', finalUrl);
                else if (finalUrl !== location.href) history.replaceState(null, '', finalUrl);

                const anchorId = anchor?.id;
                const before = anchor?.getBoundingClientRect().top;
                this.$refs.panels.innerHTML = fresh.innerHTML;
                const after = anchorId && document.getElementById(anchorId);
                if (after) window.scrollBy(0, after.getBoundingClientRect().top - before);

                this.$nextTick(() => window.renderSoalMath?.(this.$refs.panels));
                return true;
            } catch (e) {
                this.notify(e.message, false);
                return false;
            } finally {
                this.busy = false;
            }
        },

        /** Pasang / lepas soal tanpa reload halaman. */
        async submitSoal(form, confirmText = null) {
            if (this.busy || (confirmText && ! confirm(confirmText))) return;
            this.busy = true;
            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json().catch(() => ({}));
                if (res.status === 419) throw new Error('Sesi sudah habis. Muat ulang halaman (F5) lalu coba lagi.');
                if (! res.ok) throw new Error(data.message || ('Gagal menyimpan (' + res.status + ')'));

                if (await this.loadPanels(location.href, { anchor: form.closest('.card') })) {
                    this.notify(data.message, true);
                }
            } catch (e) {
                this.busy = false;
                this.notify(e.message, false);
            }
        },

        /** Cari / filter topik → hanya daftar soal yang dimuat ulang (kembali ke halaman 1). */
        async filterSoal(form) {
            const url = new URL(location.pathname, location.origin);
            for (const [key, val] of new FormData(form)) {
                if (String(val).trim() !== '') url.searchParams.set(key, String(val).trim());
            }
            await this.navigate(url.toString());
        },

        /** Link pagination & "Reset filter" (ditandai data-nav) ikut lewat AJAX. */
        onNavClick(e) {
            const a = e.target.closest('[data-nav] a[href]');
            if (! a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
            e.preventDefault();
            this.navigate(a.href);
        },

        async navigate(url) {
            if (! await this.loadPanels(url, { push: true })) return;
            const bank = document.getElementById('panel-bank-soal');
            if (bank && bank.getBoundingClientRect().top < 0) bank.scrollIntoView({ behavior: 'smooth' });
        },

        notify(message, ok) {
            if (! message) return;
            clearTimeout(this.toastTimer);
            this.toast = { show: true, ok, message };
            this.toastTimer = setTimeout(() => this.toast.show = false, ok ? 3000 : 8000);
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
window.tesSoalPage = tesSoalPage;

/**
 * Dropdown "Topik" yang bisa dicari: ketik sebagian nama topik (boleh
 * beberapa kata, urutan bebas), pilih dengan klik atau ↑ ↓ Enter.
 * Memilih topik langsung menjalankan filter (form di-submit → filterSoal()).
 */
function topikFilter(options, selected) {
    return {
        options,
        selected: selected || '',
        open: false,
        query: '',
        active: 0,

        get current() {
            return this.options.find(t => t.id === this.selected) || null;
        },
        get filtered() {
            const words = this.query.toLowerCase().split(/\s+/).filter(Boolean);
            const hits = this.options.filter(t => {
                const text = (t.label + ' ' + t.info).toLowerCase();
                return words.every(w => text.includes(w));
            });
            return words.length ? hits : [{ id: '', label: 'Semua topik', info: '', jml: null }, ...hits];
        },

        toggle() { this.open ? this.close() : this.show(); },
        show() {
            this.open = true;
            this.query = '';
            this.active = Math.max(0, this.filtered.findIndex(t => t.id === this.selected));
            this.$nextTick(() => { this.$refs.topikSearch.focus(); this.scrollToActive(); });
        },
        close() { this.open = false; },
        move(step) {
            const n = this.filtered.length;
            if (! n) return;
            this.active = (this.active + step + n) % n;
            this.$nextTick(() => this.scrollToActive());
        },
        scrollToActive() {
            this.$refs.topikList.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
        },
        pick(t) {
            if (! t) return;
            this.selected = t.id;
            this.open = false;
            this.$refs.topikInput.value = t.id;
            this.$refs.topikInput.form.requestSubmit();
        },
    };
}
window.topikFilter = topikFilter;
</script>
</div>
@endsection
