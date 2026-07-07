@extends('layouts.app')
@section('title', $item->exists ? 'Edit Soal' : 'Tambah Soal')

@php
    $typesMap = $types->mapWithKeys(fn ($t) => [$t->id => $t->slug])->toArray();
    $currentTypeId = old('question_type_id', $item->question_type_id ?? $types->first()?->id);

    // Daftar topik untuk cascade (difilter di klien berdasarkan tingkat terpilih)
    $topicsJson = $topics->map(fn ($t) => [
        'id' => $t->id, 'topic' => $t->topic, 'tingkat' => $t->tingkat, 'mapel' => $t->mata_pelajaran_id,
    ])->values();

    // PG/PGK options
    $pgOldOpts = old('options', $options->where('is_left_side', '!=', false)->pluck('option_text')->toArray());
    $pgCount = max(count($pgOldOpts), 4);
    $pgCorrect = old('correct', $options->search(fn ($o) => $o->is_correct));
    $pgkCorrect = old('correct_multi', $options->where('is_correct', true)->keys()->toArray());

    // Penjodohan
    $matchLeft  = old('match_left',  $options->where('is_left_side', true)->sortBy('order')->pluck('option_text')->values()->toArray());
    $matchRight = old('match_right', $options->where('is_left_side', false)->sortBy('order')->pluck('option_text')->values()->toArray());
    $matchCount = max(count($matchLeft), count($matchRight), 3);

    // Benar/Salah
    $bsAnswer = old('benar_salah_jawaban', $options->where('is_correct', true)->first()?->option_text === 'Benar' ? 'B' : 'S');
@endphp

@push('head')
{{-- TinyMCE 7 community (free, self-hosted lokal via public/vendor/tinymce, tanpa API key, tanpa lisensi premium, tidak butuh internet) --}}
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<style>
    .tox-tinymce { border-radius: 0.75rem !important; border-color: rgb(226 232 240) !important; }
    .symbol-btn { @apply px-2 py-1 rounded border border-slate-200 hover:bg-brand-50 hover:border-brand-300 text-sm font-medium transition; }
</style>
@endpush

@section('content')
<x-page-header :title="$item->exists ? 'Edit Soal' : 'Tambah Soal Baru'" subtitle="Editor lengkap: tabel, gambar, simbol matematika, formatting"/>

<form method="POST" action="{{ $item->exists ? route('bank-soal.update', $item) : route('bank-soal.store') }}"
      class="card card-pad space-y-5 max-w-5xl"
      x-data="bankSoalForm({
          typesMap: @js($typesMap),
          currentType: {{ (int) $currentTypeId }},
          pgCorrect: '{{ $pgCorrect }}',
          pgkCorrect: @js($pgkCorrect),
          bsAnswer: '{{ $bsAnswer }}',
          tingkat: '{{ old('tingkat', $item->tingkat) }}',
          topicId: '{{ old('topic_id', $item->topic_id) }}',
          topics: @js($topicsJson),
      })"
      x-init="initEditors()">
    @csrf @if($item->exists) @method('PUT') @endif

    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="label">Jenis Soal <span class="text-rose-500">*</span></label>
            <select name="question_type_id" class="select" x-model="currentType" @change="changeType()" required>
                @foreach($types as $t)
                    <option value="{{ $t->id }}">{{ $t->question_type }}</option>
                @endforeach
            </select>
            @error('question_type_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <x-field type="select" name="mata_pelajaran_id" label="Mata Pelajaran" :value="$item->mata_pelajaran_id"
                 :options="$mapel->pluck('nama_mapel', 'id')->toArray()"/>

        {{-- Tingkat / Kelas: dropdown dari master tingkat_kelas --}}
        <div>
            <label class="label">Tingkat / Kelas</label>
            <select name="tingkat" class="select" x-model="tingkat" @change="onTingkatChange()">
                <option value="">— Pilih tingkat —</option>
                @foreach($tingkatList as $nomor => $nama)
                    <option value="{{ $nomor }}">{{ $nama }}</option>
                @endforeach
            </select>
            @error('tingkat')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        {{-- Topik: muncul setelah memilih tingkat, difilter sesuai tingkat --}}
        <div>
            <label class="label">Topik</label>
            <select name="topic_id" class="select" x-model="topicId" :disabled="!tingkat"
                    x-init="$nextTick(() => { if (topicId) $el.value = topicId })">
                <option value="">— Pilih topik —</option>
                <template x-for="t in filteredTopics" :key="t.id">
                    <option :value="String(t.id)" x-text="t.topic"></option>
                </template>
            </select>
            <p x-show="!tingkat" x-cloak class="mt-1 text-xs text-ink-500">Pilih tingkat kelas dulu untuk memuat topik.</p>
            <p x-show="tingkat && filteredTopics.length === 0" x-cloak class="mt-1 text-xs text-amber-600">Belum ada topik untuk tingkat ini. Tambahkan lewat menu Topik.</p>
            @error('topic_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <x-field name="title" label="Judul Singkat" :value="$item->title" required placeholder="Pendekatan persamaan kuadrat"/>

    {{-- ====================== EDITOR SOAL (CKEditor 5) ====================== --}}
    <div>
        <div class="flex items-center justify-between mb-1.5">
            <label class="label mb-0" for="editor-question">
                Pertanyaan <span class="text-rose-500">*</span>
            </label>
            <button type="button" @click="showSymbols = !showSymbols; symbolTarget = 'question'"
                    class="text-xs text-brand-600 hover:underline">∑ Sisip Simbol Matematika</button>
        </div>
        <textarea name="question" id="editor-question" data-editor="full">{{ old('question', $item->question) }}</textarea>
        @error('question')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    {{-- ====================== PALETTE SIMBOL MATEMATIKA ====================== --}}
    <div x-show="showSymbols" x-cloak x-transition class="card card-pad bg-slate-50 border-brand-200">
        <div class="flex items-center justify-between mb-2">
            <div class="text-sm font-semibold text-ink-900">Klik untuk menyisipkan ke editor "<span x-text="symbolTarget"></span>"</div>
            <button type="button" @click="showSymbols = false" class="text-ink-500 hover:text-ink-900 text-lg">×</button>
        </div>
        <div class="grid grid-cols-10 sm:grid-cols-14 gap-1 text-base">
            @php
                $symbols = [
                    // Operator
                    '+','−','×','÷','±','=','≠','≈','≡','<','>','≤','≥',
                    // Pangkat & akar
                    '²','³','⁴','⁵','ⁿ','√','∛','∜','π','∞',
                    // Yunani
                    'α','β','γ','δ','θ','λ','μ','σ','φ','ω','Δ','Σ','Ω',
                    // Fraksi & set
                    '½','⅓','¼','¾','⅔','⅕','⅛','∈','∉','⊂','⊆','∪','∩','∅',
                    // Lain-lain
                    '°','′','″','∠','‖','∴','∵','∝','→','←','↑','↓','↔','⇒','⇔',
                    // Integral / kalkulus
                    '∫','∬','∭','∮','∂','∇','∑','∏','log','ln','sin','cos','tan',
                ];
            @endphp
            @foreach($symbols as $sym)
                <button type="button"
                        @mousedown.prevent
                        @click="insertSymbol(@js($sym))"
                        class="symbol-btn">{{ $sym }}</button>
            @endforeach
        </div>
        <div class="mt-2 text-[10px] text-ink-500">
            Tip: untuk superscript / subscript kompleks, gunakan menu format di toolbar editor.
        </div>
    </div>

    {{-- ====================== JENIS-SPECIFIC ====================== --}}
    {{-- PILIHAN GANDA & PILIHAN GANDA KOMPLEKS (satu blok, hindari duplikasi name="options[]") --}}
    <div x-show="slug === 'pg' || slug === 'pgk'" x-cloak>
        <div class="flex items-center justify-between mb-2">
            <span class="label mb-0" x-text="slug === 'pgk' ? 'Opsi (Multi Jawaban Benar)' : 'Opsi Jawaban (Pilihan Ganda)'"></span>
            <span class="text-xs text-ink-500" x-text="slug === 'pgk' ? 'Centang semua opsi benar' : 'Pilih radio untuk opsi benar'"></span>
        </div>
        <div class="space-y-3">
            @for ($i = 0; $i < $pgCount; $i++)
                <div class="card card-pad py-3 space-y-2">
                    <div class="flex items-center gap-2">
                        {{-- PG: radio (di-disable saat mode PGK agar tidak ikut terkirim) --}}
                        <input type="radio" name="correct" value="{{ $i }}" x-model="pgCorrect"
                               x-show="slug === 'pg'" :disabled="slug !== 'pg'"
                               class="text-brand-600 focus:ring-brand-500 border-slate-300">
                        {{-- PGK: checkbox (di-disable saat mode PG) --}}
                        <input type="checkbox" name="correct_multi[]" value="{{ $i }}"
                               x-show="slug === 'pgk'" :disabled="slug !== 'pgk'"
                               @checked(in_array((string) $i, array_map('strval', (array) $pgkCorrect), true))
                               class="rounded text-brand-600 focus:ring-brand-500 border-slate-300">
                        <span class="w-7 text-center font-bold text-ink-700">{{ chr(65 + $i) }}</span>
                        <button type="button" @click="symbolTarget = 'opsi {{ chr(65 + $i) }}'; showSymbols = true"
                                class="text-[10px] text-brand-600 hover:underline ml-auto">∑ simbol</button>
                    </div>
                    <textarea name="options[{{ $i }}]" data-editor="mini" data-opsi-label="opsi {{ chr(65 + $i) }}"
                              class="opsi">{{ $pgOldOpts[$i] ?? '' }}</textarea>
                </div>
            @endfor
        </div>
    </div>

    {{-- FILL THE BLANK --}}
    <div x-show="slug === 'fill-blank'" x-cloak class="grid md:grid-cols-2 gap-4">
        <x-field name="correct_answer_text" label="Kunci Jawaban" :value="$item->correct_answer_text"
                 placeholder="Jakarta" help="Pisahkan dengan | jika ada beberapa jawaban diterima"/>
        <x-field type="checkbox" name="case_sensitive" :value="$item->case_sensitive ?? false"
                 help="Bedakan huruf kapital/kecil"/>
    </div>

    {{-- PENJODOHAN --}}
    <div x-show="slug === 'penjodohan'" x-cloak>
        <span class="label">Pasangan Kiri ↔ Kanan</span>
        <div class="grid grid-cols-[2rem_1fr_1.5rem_1fr] gap-2 mb-1 text-xs font-semibold text-ink-500">
            <div></div><div>Kolom Kiri</div><div></div><div>Pasangan Kanan</div>
        </div>
        <div class="space-y-2">
            @for ($i = 0; $i < $matchCount; $i++)
                <div class="grid grid-cols-[2rem_1fr_1.5rem_1fr] gap-2 items-center">
                    <span class="text-center font-bold text-ink-500">{{ chr(65 + $i) }}</span>
                    <input type="text" name="match_left[{{ $i }}]" value="{{ $matchLeft[$i] ?? '' }}" class="input" placeholder="Item {{ chr(65 + $i) }}">
                    <span class="text-center text-ink-400">↔</span>
                    <input type="text" name="match_right[{{ $i }}]" value="{{ $matchRight[$i] ?? '' }}" class="input" placeholder="Pasangan {{ $i + 1 }}">
                </div>
            @endfor
        </div>
    </div>

    {{-- BENAR / SALAH --}}
    <div x-show="slug === 'benar-salah'" x-cloak>
        <span class="label">Kunci Jawaban</span>
        <div class="grid grid-cols-2 gap-3 max-w-md">
            <label class="card card-pad flex items-center gap-3 cursor-pointer has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                <input type="radio" name="benar_salah_jawaban" value="B" x-model="bsAnswer" class="text-emerald-600 focus:ring-emerald-500">
                <span class="font-semibold text-emerald-700">Benar</span>
            </label>
            <label class="card card-pad flex items-center gap-3 cursor-pointer has-[:checked]:border-rose-500 has-[:checked]:bg-rose-50">
                <input type="radio" name="benar_salah_jawaban" value="S" x-model="bsAnswer" class="text-rose-600 focus:ring-rose-500">
                <span class="font-semibold text-rose-700">Salah</span>
            </label>
        </div>
    </div>

    <x-field type="checkbox" name="is_active" :value="$item->is_active ?? true"/>

    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
        <a href="{{ route('bank-soal.index') }}" class="btn-secondary">Batal</a>
        <button class="btn-primary">Simpan Soal</button>
    </div>
</form>

{{-- ====================== JAVASCRIPT (TinyMCE 7) ====================== --}}
<script>
const TINY_UPLOAD_URL = '{{ route('bank-soal.upload-image') }}';
const TINY_CSRF       = document.querySelector('meta[name=csrf-token]').content;

const editorRegistry = []; // [{ editor, kind }]
let lastFocusedEditor = null;

function waitForTiny() {
    return new Promise(resolve => {
        if (window.tinymce) return resolve();
        const t = setInterval(() => {
            if (window.tinymce) { clearInterval(t); resolve(); }
        }, 50);
    });
}

// Custom uploader untuk TinyMCE — match dengan response controller Laravel
function imagesUploadHandler(blobInfo) {
    return new Promise((resolve, reject) => {
        const fd = new FormData();
        fd.append('upload', blobInfo.blob(), blobInfo.filename());
        fetch(TINY_UPLOAD_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TINY_CSRF, 'Accept': 'application/json' },
            body: fd,
        })
        .then(r => r.json().then(data => ({ status: r.status, data })))
        .then(({ status, data }) => {
            if (status >= 400 || data.error) {
                reject(data.error?.message || 'Gagal upload gambar (status '+status+')');
                return;
            }
            resolve(data.url || data.location);
        })
        .catch(err => reject(err.message || 'Network error saat upload gambar'));
    });
}

function bankSoalForm({ typesMap, currentType, pgCorrect, pgkCorrect, bsAnswer, tingkat, topicId, topics }) {
    return {
        typesMap, currentType, slug: typesMap[currentType] || 'pg',
        pgCorrect, pgkCorrect, bsAnswer,
        tingkat: tingkat || '', topicId: topicId || '', topics: topics || [],
        showSymbols: false, symbolTarget: 'pertanyaan',

        // Topik yang cocok dengan tingkat terpilih
        get filteredTopics() {
            if (!this.tingkat) return [];
            return this.topics.filter(t => String(t.tingkat) === String(this.tingkat));
        },

        // Saat tingkat berubah, reset topik jika tak lagi valid
        onTingkatChange() {
            if (! this.filteredTopics.some(t => String(t.id) === String(this.topicId))) {
                this.topicId = '';
            }
        },

        changeType() { this.slug = this.typesMap[this.currentType] || 'pg'; },

        async initEditors() {
            await waitForTiny();
            await this.createEditor('textarea#editor-question', 'full');
            await this.createEditor('textarea[data-editor="mini"]', 'mini');
        },

        async createEditor(selector, kind) {
            const baseConfig = {
                selector,
                license_key: 'gpl',         // TinyMCE 7 free license
                promotion: false,
                branding: false,
                menubar: false,
                statusbar: false,
                paste_data_images: true,
                automatic_uploads: true,
                images_upload_handler: imagesUploadHandler,
                images_file_types: 'png,jpg,jpeg,gif,webp,svg',
                content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; line-height: 1.5; }',
                setup: (editor) => {
                    editor.on('focus click keyup', () => { lastFocusedEditor = editor; });
                },
            };

            const config = (kind === 'full') ? {
                ...baseConfig,
                height: 320,
                plugins: 'image table lists link advlist autolink media charmap emoticons searchreplace anchor visualblocks code preview wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table media | charmap emoticons | removeformat | code preview',
            } : {
                ...baseConfig,
                height: 140,
                plugins: 'image lists link charmap',
                toolbar: 'bold italic underline | forecolor | bullist numlist | image charmap | removeformat',
            };

            try {
                const editors = await tinymce.init(config);
                editors.forEach(ed => editorRegistry.push({ editor: ed, kind }));
                if (kind === 'full' && !lastFocusedEditor && editors[0]) {
                    lastFocusedEditor = editors[0];
                }
            } catch (e) {
                console.error('TinyMCE init error:', e);
                alert('Gagal memuat editor: ' + (e.message || e));
            }
        },

        insertSymbol(sym) {
            const editor = lastFocusedEditor || editorRegistry.find(x => x.kind === 'full')?.editor;
            if (!editor) {
                alert('Editor belum siap. Coba lagi.');
                return;
            }
            editor.focus();
            editor.insertContent(sym);
            lastFocusedEditor = editor;
        },
    };
}
window.bankSoalForm = bankSoalForm;
</script>
@endsection
