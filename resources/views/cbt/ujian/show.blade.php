<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1, minimum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $quiz->name }} &middot; Ujian</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('partials.katex')
    <style>
        .no-select { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        body { overscroll-behavior: contain; }
        /* Sembunyikan tombol "open in new tab" pada mobile */
        a[href], img { -webkit-touch-callout: none; }

        /* Kunci zoom lapis pertama, di level browser.
           `pan-x pan-y` = scroll tetap boleh, pinch-zoom & double-tap zoom tidak.
           Ini menutup celah meta viewport `user-scalable=no` yang sengaja
           DIABAIKAN iOS Safari. Kenapa zoom dikunci: pinch-zoom mengecilkan
           angka viewport di Chrome Android sehingga terbaca sebagai layar
           terbelah oleh detektor anti-curang -- lihat _attachZoomLock() di
           resources/js/stores/examProtection.js. */
        html, body { touch-action: pan-x pan-y; }
    </style>
</head>
<body class="h-full bg-slate-100 no-select"
      x-data="cbtExam({
          endsAt: '{{ $endsAt->toIso8601String() }}',
          saveUrl: '{{ route('siswa.ujian.save', [$quiz, $attempt]) }}',
          blockedUrl: '{{ route('siswa.ujian.blocked', [$quiz, $attempt]) }}',
          pingUrl: '{{ route('siswa.ujian.ping', [$quiz, $attempt]) }}',
          loginUrl: '{{ route('login') }}',
          initialViolations: {{ (int) $attempt->violation_count }},
          existing: @js($existingAnswers->mapWithKeys(fn ($a) => [$a->quiz_question_id => $a->question_option_id ?? $a->answer_text])->toArray())
      })">

{{-- ============ START GATE (Vue: ExamStartGate.vue) ============ --}}
<div id="exam-start-gate-root"></div>

{{-- ============ HEADER ============ --}}
<header class="sticky top-0 z-20 bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-3 sm:px-6 h-16 flex items-center justify-between gap-2 sm:gap-3">
        <div class="min-w-0">
            <div class="text-xs text-green-500 truncate">Ujian — {{ auth()->user()->nama_siswa ?? '' }}</div>
            <h1 class="text-sm sm:text-base font-bold text-black truncate">{{ $quiz->name }}</h1>
        </div>
        {{-- shrink-0: timer & tombol Selesai tidak boleh terdesak di layar kecil;
             yang mengalah adalah judul ujian di kiri (sudah truncate). --}}
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            @if($protectionEnabled)
                <div class="text-right hidden sm:block">
                    <div class="text-xs text-ink-500">Pelanggaran</div>
                    <div class="text-sm font-bold"
                         :class="violations >= {{ $maxViolations - 1 }} ? 'text-rose-600 animate-pulse' : (violations > 0 ? 'text-amber-600' : 'text-emerald-600')">
                        <span x-text="violations"></span> / {{ $maxViolations }}
                    </div>
                </div>
            @endif
            <div class="text-right">
                <div class="text-[10px] sm:text-xs text-ink-500">Sisa waktu</div>
                <div class="text-lg sm:text-xl font-bold tabular-nums text-rose-600"
                     :class="seconds < 60 ? 'animate-pulse' : ''" x-text="formatted"></div>
            </div>
            <form method="POST" action="{{ route('siswa.ujian.submit', [$quiz, $attempt]) }}"
                  x-ref="submitForm">
                @csrf
                <button type="button" @click="confirmSubmit = true" class="btn-primary text-sm px-3 sm:px-4">Selesai</button>
            </form>
        </div>
    </div>

    {{-- ============ BANNER PELANGGARAN (Vue: ExamViolationBanner.vue) ============ --}}
    <div id="exam-violation-banner-root"></div>

    {{-- Banner WAKTU HABIS (sesaat sebelum auto-submit) --}}
    <div x-show="timeOverShown" x-cloak x-transition
         class="bg-amber-500 text-white px-4 py-3 text-center font-bold animate-pulse">
         Waktu Habis! Jawaban Anda otomatis dikirim ke server...
    </div>

    {{-- Banner GAGAL SIMPAN — sengaja TIDAK auto-dismiss (harus diklik Tutup
         sendiri) supaya siswa benar-benar sadar, bukan sekadar berkedip lalu
         hilang sementara dia sedang menatap soal berikutnya. --}}
    <div x-show="saveError" x-cloak x-transition
         class="bg-rose-600 text-white px-4 py-3 text-center font-bold flex items-center justify-center gap-3 flex-wrap">
         <span>⚠ Jawaban gagal tersimpan ke server. Periksa koneksi internet Anda, lalu jawab ulang soal yang tandanya belum tersimpan.</span>
         <button type="button" @click="saveError = false" class="underline shrink-0">Tutup</button>
    </div>
</header>

{{-- ============ KONTEN SOAL ============ --}}
<div class="max-w-6xl mx-auto px-3 sm:px-6 py-6 grid lg:grid-cols-[1fr_280px] gap-4 sm:gap-6">
    <div class="space-y-4">
        @foreach($quiz->questions as $idx => $qq)
            @php
                $q = $qq->question;
                $typeSlug = strtolower((string) (optional($q->type)->slug ?? optional($q->type)->question_type ?? ''));
                $isFillBlank = $typeSlug === 'fill-blank' || $typeSlug === 'fill_blank' || str_contains($typeSlug, 'fill');
            @endphp
            <div class="card card-pad soal-math" id="soal-{{ $qq->id }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs text-ink-500">Soal {{ $idx + 1 }} dari {{ $quiz->questions->count() }}</div>
                    <span class="badge-muted">{{ $qq->marks }} poin</span>
                </div>
                <div class="font-semibold text-ink-900 mb-3">{{ $q->title }}</div>
                <div class="prose prose-sm max-w-none text-ink-700 mb-4">{!! \App\Support\SoalHtml::render($q->question) !!}</div>

                @if($isFillBlank)
                    <input type="text" name="q_{{ $qq->id }}" autocomplete="off"
                           value="{{ ($existingAnswers[$qq->id] ?? null)?->answer_text }}"
                           @change="saveTextAnswer({{ $qq->id }}, $event.target.value)"
                           placeholder="Tulis jawaban Anda di sini..."
                           class="w-full p-3 rounded-xl border border-slate-200 focus:border-brand-500 focus:ring-1 focus:ring-brand-500 outline-none text-sm">
                @else
                    <div class="space-y-2">
                        @foreach($q->options as $opt)
                            <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                                <input type="radio" name="q_{{ $qq->id }}" value="{{ $opt->id }}"
                                       @checked(($existingAnswers[$qq->id] ?? null)?->question_option_id == $opt->id)
                                       @change="saveAnswer({{ $qq->id }}, $event.target.value)"
                                       class="mt-0.5 text-brand-600 focus:ring-brand-500 border-slate-300">
                                <div class="text-sm prose prose-sm max-w-none">{!! \App\Support\SoalHtml::render($opt->option_text) !!}</div>
                            </label>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <aside class="lg:sticky lg:top-28 h-fit space-y-4">
        <div class="card card-pad">
            <h3 class="text-sm font-semibold text-ink-900 mb-3">Navigasi Soal</h3>
            <div class="grid grid-cols-5 gap-2">
                @foreach($quiz->questions as $idx => $qq)
                    <button type="button" @click="document.getElementById('soal-{{ $qq->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            :class="answered[{{ $qq->id }}] ? 'bg-brand-600 text-white' : 'bg-slate-100 text-ink-700 hover:bg-slate-200'"
                            class="aspect-square rounded-lg text-sm font-semibold transition">
                        {{ $idx + 1 }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ============ KARTU ATURAN (Vue: ExamRulesCard.vue) ============ --}}
        <div id="exam-rules-card-root"></div>
    </aside>
</div>

{{-- ============ MODAL KONFIRMASI SELESAI (dalam fullscreen) ============ --}}
<div x-show="confirmSubmit" x-cloak x-transition
     class="fixed inset-0 z-[60] bg-ink-900/70 backdrop-blur grid place-items-center p-4 sm:p-6"
     @keydown.escape.window="confirmSubmit = false">
    <div class="card max-w-md w-full p-6 text-center" @click.outside="confirmSubmit = false">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-brand-50 text-brand-600 grid place-items-center mb-3 text-2xl">📤</div>
        <h3 class="text-lg font-bold text-ink-900">Selesaikan Ujian?</h3>
        <p class="text-sm text-ink-600 mt-2">
            Jawaban Anda akan dikirim dan <strong>tidak bisa diubah lagi</strong>.<br>
            Pastikan semua soal sudah dijawab.
        </p>

        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
            <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-2">
                <div class="text-emerald-700 font-semibold">Terjawab</div>
                <div class="text-lg font-bold text-emerald-700" x-text="Object.keys(answered).length"></div>
            </div>
            <div class="rounded-lg bg-amber-50 border border-amber-200 p-2">
                <div class="text-amber-700 font-semibold">Belum</div>
                <div class="text-lg font-bold text-amber-700"
                     x-text="{{ $quiz->questions->count() }} - Object.keys(answered).length"></div>
            </div>
        </div>

        <div class="flex gap-2 mt-5">
            <button type="button" @click="confirmSubmit = false" class="btn-secondary flex-1">Batal</button>
            <button type="button" @click="confirmSubmit = false; $refs.submitForm.submit()" class="btn-primary flex-1">
                Ya, Kirim
            </button>
        </div>
    </div>
</div>

{{-- ============ ALERT: AKUN DIPAKAI DI PERANGKAT LAIN ============
     Muncul saat perangkat ini ditendang SingleSessionGuard karena akun yang
     sama login di perangkat lain. z-index paling tinggi & tanpa tombol tutup:
     sesi di perangkat ini memang sudah mati, satu-satunya jalan adalah keluar. --}}
{{-- Sengaja TANPA x-transition. Transisi Alpine memakai requestAnimationFrame,
     dan rAF berhenti saat halaman tidak terlihat (siswa pindah aplikasi). Kalau
     tendangan datang tepat saat itu, animasinya bisa tertahan di opacity 0 dan
     alert sepenting ini tidak pernah kelihatan. Tampil langsung saja. --}}
<div x-show="kicked" x-cloak
     class="fixed inset-0 z-[80] bg-ink-900/80 backdrop-blur grid place-items-center p-4 sm:p-6">
    <div class="card max-w-md w-full p-6 text-center">
        <div class="mx-auto w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 grid place-items-center mb-3 text-2xl">🔒</div>
        <h3 class="text-lg font-bold text-ink-900" x-text="kickedTitle"></h3>
        <p class="text-sm text-ink-600 mt-2" x-text="kickedBody"></p>
        <a href="{{ route('login') }}" class="btn-primary w-full mt-5 justify-center">Kembali ke Halaman Login</a>
        <p class="text-xs text-ink-500 mt-2">
            Otomatis dialihkan dalam <span x-text="kickedCountdown"></span> detik…
        </p>
    </div>
</div>

{{-- Config proteksi ujian untuk store Vue (resources/js/stores/examProtection.js). --}}
<script type="application/json" id="exam-protection-config">{!! json_encode([
    'quizName' => $quiz->name,
    'maxViolations' => $maxViolations,
    'initialViolations' => (int) $attempt->violation_count,
    'protectionEnabled' => (bool) $protectionEnabled,
    'soundEnabled' => (bool) $violationSoundEnabled,
    'proteksiMode' => $quiz->proteksi_mode,
    'nilaiPengurangan' => (float) ($quiz->nilai_pengurangan ?? 0),
    'violationUrl' => route('siswa.ujian.violation', [$quiz, $attempt]),
    'blockedUrl' => route('siswa.ujian.blocked', [$quiz, $attempt]),
    'loginUrl' => route('login'),
]) !!}</script>

<script>
function cbtExam(cfg) {
    return {
        endsAt: new Date(cfg.endsAt).getTime(),
        saveUrl: cfg.saveUrl,
        blockedUrl: cfg.blockedUrl,
        pingUrl: cfg.pingUrl,
        loginUrl: cfg.loginUrl,

        seconds: 0,
        formatted: '00:00:00',
        answered: { ...cfg.existing },
        violations: cfg.initialViolations || 0,

        confirmSubmit: false,
        timeOverShown: false,
        autoSubmitted: false,
        saveError: false,

        // Perangkat ini ditendang karena akun dipakai login di perangkat lain.
        kicked: false,
        kickedCountdown: 8,
        kickedTitle: '',
        kickedBody: '',

        init() {
            // Timer HANYA dimulai lewat callback ini -- baik untuk quiz dengan
            // proteksi aktif (dipanggil oleh examProtectionStore.startExam()
            // setelah siswa klik "Mulai Ujian") maupun proteksi nonaktif
            // (dipanggil langsung dari examProtectionStore.init()).
            examProtectionStore.onExamStarted = () => { this.startTimer(); this.startHeartbeat(); };
            examProtectionStore.onViolationsChanged = (n) => { this.violations = n; };

            // Store juga perlu bisa memunculkan alert ini, karena fetch lapor
            // pelanggaran bisa jadi yang lebih dulu kena 409 daripada heartbeat.
            examProtectionStore.onSessionConflict = (status) => this.handleKickedResponse({ status });
        },

        startTimer() {
            this.tick();
            setInterval(() => this.tick(), 1000);
        },

        /**
         * Denyut ke server tiap 10 detik. Halaman ujian nyaris tidak pernah
         * pindah halaman, jadi tanpa denyut ini perangkat lama baru sadar sudah
         * ditendang saat siswa kebetulan menjawab soal -- bisa belasan menit.
         */
        startHeartbeat() {
            if (! this.pingUrl) return;
            setInterval(async () => {
                if (this.kicked || this.autoSubmitted) return;
                try {
                    const r = await fetch(this.pingUrl, { headers: this.headers() });
                    if (this.handleKickedResponse(r)) return;
                    if (r.ok) {
                        const data = await r.json();
                        if (data.blocked) this.goToBlocked();
                    }
                } catch (e) { /* jaringan putus sesaat -- coba lagi denyut berikutnya */ }
            }, 10000);
        },

        /**
         * Deteksi "sesi di perangkat ini sudah tidak berlaku". Return true
         * kalau sudah ditangani, supaya pemanggil berhenti memproses respons.
         *
         *  409 = SingleSessionGuard: akun barusan login di perangkat lain.
         *        Ini status yang muncul pada request PERTAMA setelah ditendang.
         *  401 = sesi sudah dihanguskan (request kedua dst, atau sesi kedaluwarsa).
         *  419 = token CSRF ikut mati bersama sesinya.
         *
         * 401/419 ikut ditangani supaya siswa tidak pernah terdampar di halaman
         * ujian yang sudah mati -- mis. kalau request pertama pasca-tendangan
         * kebetulan gagal karena jaringan, yang tersisa hanya 401/419.
         */
        handleKickedResponse(r) {
            if (r.status === 409) {
                this.showKicked(
                    'Akun Anda sedang dilogin di perangkat lain',
                    'Ujian di perangkat ini dihentikan karena akun Anda baru saja dipakai login di perangkat lain. Jawaban yang sudah tersimpan tetap aman dan bisa dilanjutkan dari perangkat tersebut.'
                );
                return true;
            }
            if (r.status === 401 || r.status === 419) {
                this.showKicked(
                    'Sesi ujian Anda sudah berakhir',
                    'Sesi login di perangkat ini sudah tidak berlaku. Jawaban yang sudah tersimpan tetap aman — silakan login kembali untuk melanjutkan.'
                );
                return true;
            }
            return false;
        },

        showKicked(judul, isi) {
            if (this.kicked) return;
            this.kickedTitle = judul || 'Akun Anda sedang dilogin di perangkat lain';
            this.kickedBody = isi || 'Ujian di perangkat ini dihentikan karena akun Anda baru saja dipakai login di perangkat lain. Jawaban yang sudah tersimpan tetap aman.';
            this.kicked = true;

            // Hentikan proteksi anti-curang: siswa akan meninggalkan halaman ini
            // atas perintah sistem, jangan sampai dihitung sebagai pelanggaran.
            examProtectionStore.protectionEnabled = false;

            const timer = setInterval(() => {
                this.kickedCountdown--;
                if (this.kickedCountdown <= 0) {
                    clearInterval(timer);
                    window.location.replace(this.loginUrl);
                }
            }, 1000);
        },

        tick() {
            const left = Math.max(0, Math.floor((this.endsAt - Date.now()) / 1000));
            this.seconds = left;
            const h = String(Math.floor(left/3600)).padStart(2,'0');
            const m = String(Math.floor((left%3600)/60)).padStart(2,'0');
            const s = String(left%60).padStart(2,'0');
            this.formatted = `${h}:${m}:${s}`;

            // ===== AUTO-SUBMIT SAAT WAKTU HABIS =====
            if (left === 0 && ! this.autoSubmitted) {
                this.autoSubmitted = true;
                this.timeOverShown = true;
                // Tampilkan banner 1.5 detik supaya siswa sadar, lalu submit
                setTimeout(() => {
                    const form = document.querySelector('form[action$="/submit"]');
                    if (form) form.submit();
                }, 1500);
            }
        },

        async saveAnswer(qqId, optionId) {
            const previous = this.answered[qqId];
            this.answered[qqId] = optionId;
            try {
                const r = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ quiz_question_id: qqId, question_option_id: optionId })
                });
                if (this.handleKickedResponse(r)) return;
                if (r.status === 423) { this.goToBlocked(); return; }
                if (! r.ok) this.markSaveFailed(qqId, previous);
            } catch (e) { this.markSaveFailed(qqId, previous); }
        },

        async saveTextAnswer(qqId, text) {
            const previous = this.answered[qqId];
            if (text.trim() === '') { delete this.answered[qqId]; } else { this.answered[qqId] = text; }
            try {
                const r = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ quiz_question_id: qqId, answer_text: text })
                });
                if (this.handleKickedResponse(r)) return;
                if (r.status === 423) { this.goToBlocked(); return; }
                if (! r.ok) this.markSaveFailed(qqId, previous);
            } catch (e) { this.markSaveFailed(qqId, previous); }
        },

        /**
         * Sebelumnya kegagalan simpan (selain 409/401/419/423 yang sudah
         * ditangani) DIAM SAJA -- cuma console.error, sementara tanda
         * "terjawab" di this.answered TETAP menyala hijau karena sudah
         * di-set optimis sebelum request. Siswa mengira jawabannya
         * tersimpan padahal server menolak (mis. soal itu baru saja
         * dihapus dari tes oleh guru -> 422 "quiz_question_id" tidak
         * valid lagi) atau request gagal jaringan -- dan itu baru
         * ketahuan setelah submit, saat nilainya sudah telanjur 0.
         * Balikkan tanda ke keadaan sebelumnya (jujur: belum tersimpan)
         * dan tampilkan banner supaya siswa tahu harus mengulang.
         */
        markSaveFailed(qqId, previous) {
            if (previous === undefined) { delete this.answered[qqId]; } else { this.answered[qqId] = previous; }
            this.saveError = true;
        },

        /** Pindah ke halaman blokir tanpa prompt browser (dipakai saat saveAnswer ditolak server, terlepas dari counting pelanggaran Vue). */
        goToBlocked() {
            window.location.replace(this.blockedUrl);
        },

        headers() {
            return {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                'Accept': 'application/json',
            };
        }
    };
}
window.cbtExam = cbtExam;
</script>
</body>
</html>
