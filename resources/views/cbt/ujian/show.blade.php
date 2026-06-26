<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1, minimum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ $quiz->name }} &middot; Ujian</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .no-select { -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; }
        body { overscroll-behavior: contain; }
        /* Sembunyikan tombol "open in new tab" pada mobile */
        a[href], img { -webkit-touch-callout: none; }
    </style>
</head>
<body class="h-full bg-slate-100 no-select"
      x-data="cbtExam({
          endsAt: '{{ $endsAt->toIso8601String() }}',
          saveUrl: '{{ route('siswa.ujian.save', [$quiz, $attempt]) }}',
          violationUrl: '{{ route('siswa.ujian.violation', [$quiz, $attempt]) }}',
          blockedUrl: '{{ route('siswa.ujian.blocked', [$quiz, $attempt]) }}',
          maxViolations: {{ $maxViolations }},
          initialViolations: {{ (int) $attempt->violation_count }},
          protectionEnabled: {{ $protectionEnabled ? 'true' : 'false' }},
          existing: @js($existingAnswers->mapWithKeys(fn ($a) => [$a->quiz_question_id => $a->question_option_id])->toArray())
      })"
      @contextmenu.prevent="logViolation('right_click')"
      @copy.prevent="logViolation('copy')"
      @paste.prevent="logViolation('paste')"
      @cut.prevent="logViolation('cut')">

{{-- ============ START GATE: harus klik "Mulai" agar fullscreen tepat diaktifkan via user gesture ============ --}}
<div x-show="protectionEnabled && needStart" x-cloak
     class="fixed inset-0 z-50 bg-ink-900/95 backdrop-blur grid place-items-center p-6">
    <div class="card max-w-lg w-full p-8 text-center">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-rose-50 text-rose-600 grid place-items-center mb-3 text-2xl">🛡️</div>
        <h2 class="text-xl font-bold text-ink-900">Ujian Berproteksi</h2>
        <p class="text-sm text-ink-600 mt-2">{{ $quiz->name }}</p>

        <div class="mt-5 text-left text-sm text-ink-700 bg-amber-50 border border-amber-200 rounded-xl p-4 space-y-1.5">
            <div class="font-semibold text-amber-800 mb-1">⚠ Aturan ketat ujian:</div>
            <div :class="isMobile ? 'hidden' : ''">• Layar otomatis <strong>fullscreen</strong> &mdash; jangan keluar</div>
            <div :class="isMobile ? '' : 'hidden'">• Jangan keluar / pindah aplikasi</div>
            <div>• Jangan pindah tab / window browser</div>
            <div>• Dilarang copy / paste / klik kanan</div>
            <div>• Dilarang membuka DevTools (F12)</div>
            <div class="font-semibold text-rose-700 pt-1.5 border-t border-amber-200">
                Maks. <strong>{{ $maxViolations }} pelanggaran</strong> — lebih dari itu, ujian <strong>OTOMATIS DIBLOKIR</strong>.
            </div>
        </div>

        <button @click="startExam()" class="btn-primary w-full mt-5 text-base py-3">
            Saya Mengerti, Mulai Ujian
        </button>
    </div>
</div>

{{-- ============ HEADER ============ --}}
<header class="sticky top-0 z-20 bg-white border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <div class="min-w-0">
            <div class="text-xs text-green-500 truncate">Ujian — {{ auth()->user()->nama_siswa ?? '' }}</div>
            <h1 class="text-base font-bold text-black truncate">{{ $quiz->name }}</h1>
        </div>
        <div class="flex items-center gap-3">
            @if($protectionEnabled)
                <div class="text-right hidden sm:block">
                    <div class="text-xs text-ink-500">Pelanggaran</div>
                    <div class="text-sm font-bold"
                         :class="violations >= maxViolations - 1 ? 'text-rose-600 animate-pulse' : (violations > 0 ? 'text-amber-600' : 'text-emerald-600')">
                        <span x-text="violations"></span> / {{ $maxViolations }}
                    </div>
                </div>
            @endif
            <div class="text-right">
                <div class="text-xs text-ink-500">Sisa waktu</div>
                <div class="text-xl font-bold tabular-nums text-rose-600"
                     :class="seconds < 60 ? 'animate-pulse' : ''" x-text="formatted"></div>
            </div>
            <form method="POST" action="{{ route('siswa.ujian.submit', [$quiz, $attempt]) }}"
                  x-ref="submitForm">
                @csrf
                <button type="button" @click="confirmSubmit = true" class="btn-primary text-sm">Selesai</button>
            </form>
        </div>
    </div>

    @if($protectionEnabled)
        <div x-show="showWarning" x-transition x-cloak
             class="bg-rose-600 text-white px-4 py-2 text-sm text-center font-semibold">
            ⚠ Pelanggaran: <span x-text="lastViolation"></span>
            (<span x-text="violations"></span>/{{ $maxViolations }}) — Ujian akan diblokir jika berlanjut!
        </div>
    @endif

    {{-- Banner WAKTU HABIS (sesaat sebelum auto-submit) --}}
    <div x-show="timeOverShown" x-cloak x-transition
         class="bg-amber-500 text-white px-4 py-3 text-center font-bold animate-pulse">
        ⏰ Waktu Habis! Jawaban Anda otomatis dikirim ke server...
    </div>
</header>

{{-- ============ KONTEN SOAL ============ --}}
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 grid lg:grid-cols-[1fr_280px] gap-6">
    <div class="space-y-4">
        @foreach($quiz->questions as $idx => $qq)
            @php $q = $qq->question; @endphp
            <div class="card card-pad" id="soal-{{ $qq->id }}">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-xs text-ink-500">Soal {{ $idx + 1 }} dari {{ $quiz->questions->count() }}</div>
                    <span class="badge-muted">{{ $qq->marks }} poin</span>
                </div>
                <div class="font-semibold text-ink-900 mb-3">{{ $q->title }}</div>
                <div class="prose prose-sm max-w-none text-ink-700 mb-4">{!! $q->question !!}</div>

                <div class="space-y-2">
                    @foreach($q->options as $opt)
                        <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-50 transition has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
                            <input type="radio" name="q_{{ $qq->id }}" value="{{ $opt->id }}"
                                   @checked(($existingAnswers[$qq->id] ?? null)?->question_option_id == $opt->id)
                                   @change="saveAnswer({{ $qq->id }}, $event.target.value)"
                                   class="mt-0.5 text-brand-600 focus:ring-brand-500 border-slate-300">
                            <div class="text-sm prose prose-sm max-w-none">{!! $opt->option_text !!}</div>
                        </label>
                    @endforeach
                </div>
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

        @if($protectionEnabled)
            <div class="card card-pad text-xs text-ink-600 space-y-1.5">
                <div class="font-semibold text-ink-900 mb-2">🛡️ Aturan Ujian</div>
                <div x-show="!isMobile">• Tetap dalam mode fullscreen</div>
                <div x-show="isMobile">• Jangan keluar / pindah aplikasi</div>
                <div>• Jangan pindah tab / window</div>
                <div>• Dilarang copy / paste / klik kanan</div>
                <div>• Dilarang membuka DevTools</div>
                <div class="pt-2 mt-2 border-t border-slate-100 text-rose-600 font-semibold">
                    > {{ $maxViolations }} pelanggaran = ujian diblokir
                </div>
            </div>
        @endif
    </aside>
</div>

{{-- ============ MODAL KONFIRMASI SELESAI (dalam fullscreen) ============ --}}
<div x-show="confirmSubmit" x-cloak x-transition
     class="fixed inset-0 z-[60] bg-ink-900/70 backdrop-blur grid place-items-center p-6"
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

<script>
function cbtExam(cfg) {
    return {
        endsAt: new Date(cfg.endsAt).getTime(),
        saveUrl: cfg.saveUrl,
        violationUrl: cfg.violationUrl,
        blockedUrl: cfg.blockedUrl,
        maxViolations: cfg.maxViolations,
        protectionEnabled: cfg.protectionEnabled,

        seconds: 0,
        formatted: '00:00:00',
        answered: { ...cfg.existing },
        violations: cfg.initialViolations || 0,

        showWarning: false,
        lastViolation: '',
        warningTimer: null,

        isMobile: false,
        needStart: cfg.protectionEnabled,
        isFullscreen: false,
        savingViolation: false,
        confirmSubmit: false,
        timeOverShown: false,
        autoSubmitted: false,

        init() {
            this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                            || (window.matchMedia && window.matchMedia('(pointer:coarse)').matches);

            if (!cfg.protectionEnabled) {
                this.needStart = false;
                this.startTimer();
                return;
            }

            // Saat protection on, kita TUNGGU user klik tombol Mulai
            // (karena requestFullscreen butuh user gesture)
        },

        async startExam() {
            this.needStart = false;
            this.startTimer();

            if (!this.isMobile) {
                // === DESKTOP: WAJIB FULLSCREEN ===
                try {
                    await document.documentElement.requestFullscreen({ navigationUI: 'hide' });
                    this.isFullscreen = true;
                } catch (e) {
                    this.logViolation('fullscreen_denied', 'Browser menolak fullscreen');
                }

                document.addEventListener('fullscreenchange', () => {
                    this.isFullscreen = !!document.fullscreenElement;
                    if (!this.isFullscreen) {
                        this.logViolation('fullscreen_exit');
                        // Coba paksa masuk fullscreen lagi
                        setTimeout(() => {
                            document.documentElement.requestFullscreen?.().catch(() => {});
                        }, 100);
                    }
                });
            } else {
                // === MOBILE: deteksi orientasi & rotasi yang aneh ===
                if (screen.orientation && screen.orientation.lock) {
                    try { await screen.orientation.lock('portrait'); } catch (e) {}
                }
                screen.orientation?.addEventListener('change', () => {
                    this.logViolation('orientation_change', screen.orientation?.type);
                });

                // Mobile: deteksi touch dengan multi-finger (split-screen di Android sering memicu)
                document.addEventListener('touchstart', (e) => {
                    if (e.touches.length > 2) this.logViolation('multi_touch');
                }, { passive: true });
            }

            this.attachCommonHandlers();
        },

        attachCommonHandlers() {
            // 1. Tab / window blur
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.logViolation(this.isMobile ? 'app_switch' : 'tab_switch');
            });
            window.addEventListener('blur', () => this.logViolation('window_blur'));
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) this.logViolation('back_forward_cache');
            });

            // 2. Prevent F12, Ctrl+Shift+I, Ctrl+U, Ctrl+S, Ctrl+P
            document.addEventListener('keydown', (e) => {
                const blocked =
                    e.key === 'F12' ||
                    (e.ctrlKey && e.shiftKey && ['I','J','C','K'].includes(e.key.toUpperCase())) ||
                    (e.ctrlKey && ['U','S','P','A'].includes(e.key.toUpperCase())) ||
                    (e.metaKey && ['I','U','S','P'].includes(e.key.toUpperCase())); // Mac
                if (blocked) {
                    e.preventDefault();
                    this.logViolation('blocked_key', e.key);
                }
            });

            // 3. DevTools detection (heuristic)
            setInterval(() => {
                const w = window.outerWidth - window.innerWidth;
                const h = window.outerHeight - window.innerHeight;
                if (!this.isMobile && (w > 200 || h > 200)) {
                    this.logViolation('devtools');
                }
            }, 3000);

            // 4. Drag & drop / select text
            document.addEventListener('dragstart', (e) => { e.preventDefault(); });
            document.addEventListener('selectstart', (e) => {
                // bolehkan input field
                if (e.target.matches('input, textarea')) return;
                e.preventDefault();
            });
        },

        /** Pindah ke halaman blokir tanpa prompt browser */
        goToBlocked() {
            // pakai replace agar tidak bisa "back"
            window.location.replace(this.blockedUrl);
        },

        startTimer() {
            this.tick();
            setInterval(() => this.tick(), 1000);
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
            this.answered[qqId] = optionId;
            try {
                const r = await fetch(this.saveUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ quiz_question_id: qqId, question_option_id: optionId })
                });
                if (r.status === 423) this.goToBlocked();
            } catch (e) { console.error(e); }
        },

        async logViolation(type, detail = null) {
            if (!this.protectionEnabled) return;
            if (this.savingViolation) return;

            this.violations++;
            this.lastViolation = type;
            this.showWarning = true;
            clearTimeout(this.warningTimer);
            this.warningTimer = setTimeout(() => this.showWarning = false, 4000);

            this.savingViolation = true;
            try {
                const r = await fetch(this.violationUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify({ type, detail })
                });
                const data = await r.json();
                if (data.blocked) {
                    // Mode "blokir" → halaman blokir
                    this.goToBlocked();
                } else if (data.logout) {
                    // Mode "logout_otomatis" → langsung ke halaman hasil (sudah di-submit di server)
                    window.location.replace(window.location.pathname.replace(/\/[^\/]+$/, '') + '/result');
                }
            } catch (e) { console.error(e); }
            finally { this.savingViolation = false; }
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
