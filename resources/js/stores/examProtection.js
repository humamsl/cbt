import { reactive } from 'vue';

/**
 * Shared reactive store untuk proteksi anti-curang ujian siswa.
 *
 * SATU instance untuk seluruh halaman (module ES hanya diinisialisasi sekali
 * oleh bundler), dipakai bersama oleh 3 komponen tampilan (ExamStartGate,
 * ExamViolationBanner, ExamRulesCard) di resources/js/components/, DAN dibaca
 * dari komponen Alpine (cbtExam) di show.blade.php lewat callback
 * onExamStarted/onViolationsChanged (bukan CustomEvent, supaya tidak ada
 * nama-string yang bisa typo/di-listen script lain).
 *
 * PENTING (lihat rencana implementasi): seluruh listener/interval proteksi
 * (attachCommonHandlers) dipasang dari init()/startExam() DI SINI, BUKAN dari
 * lifecycle (onMounted/onUnmounted) komponen Vue manapun -- supaya listener
 * proteksi tidak ikut lepas kalau salah satu komponen tampilan disembunyikan
 * (siklus hidup komponen tampilan != siklus hidup sesi ujian).
 *
 * Logic di bawah ini awalnya adalah portingan LANGSUNG dari fungsi cbtExam() (Alpine)
 * yang sebelumnya ada di show.blade.php -- threshold & urutan try/catch disalin persis
 * supaya tidak ada regresi perilaku dari migrasi framework. Sejak itu ditambah 1 fitur
 * baru khusus mobile (di luar scope migrasi awal, atas permintaan eksplisit): deteksi
 * heuristik split-screen/multi-window (violation type 'split_screen', lihat
 * _attachSplitScreenDetection()). Sempat juga dicoba mewajibkan fullscreen di mobile
 * (bukan cuma desktop), tapi di-revert lagi atas permintaan user -- fullscreen tetap
 * HANYA di desktop seperti semula.
 */
export const examProtectionStore = reactive({
    // ---- state proteksi ----
    isMobile: false,
    needStart: false,
    isFullscreen: false,
    violations: 0,
    showWarning: false,
    lastViolation: '',
    savingViolation: false,

    /** Info (BUKAN pelanggaran): siswa mencoba pinch-zoom yang sedang dikunci. */
    zoomHint: false,

    // ---- config, di-set sekali lewat init() ----
    quizName: '',
    maxViolations: 0,
    protectionEnabled: false,
    soundEnabled: false,
    violationUrl: '',
    blockedUrl: '',

    // ---- callback hook, di-set oleh Alpine (cbtExam) di show.blade.php ----
    onExamStarted: null,
    onViolationsChanged: null,
    /** Dipanggil saat server membalas 409: akun login di perangkat lain. */
    onSessionConflict: null,

    _warningTimer: null,
    _zoomHintTimer: null,
    _initialized: false,
    _audioCtx: null,

    /**
     * Dipanggil SEKALI secara eksplisit dari resources/js/app.js saat halaman
     * ujian dimuat (bukan dari lifecycle komponen manapun).
     */
    init(config) {
        if (this._initialized) return;
        this._initialized = true;

        this.quizName = config.quizName;
        this.maxViolations = config.maxViolations;
        this.protectionEnabled = config.protectionEnabled;
        this.soundEnabled = !!config.soundEnabled;
        this.violationUrl = config.violationUrl;
        this.blockedUrl = config.blockedUrl;
        this.violations = config.initialViolations || 0;

        this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                        || (window.matchMedia && window.matchMedia('(pointer:coarse)').matches);

        // Cegah klik-kanan/copy/paste/cut selalu aktif SE-HALAMAN, terlepas dari
        // protectionEnabled -- persis seperti directive @contextmenu.prevent
        // dkk yang dulu ada di <body x-data> tanpa dibungkus @if($protectionEnabled).
        // logViolation() sendiri yang menahan diri (no-op) kalau protection off.
        this._attachAlwaysOnHandlers();

        this.needStart = this.protectionEnabled;

        if (!this.protectionEnabled) {
            this.needStart = false;
            this.onExamStarted?.();
            return;
        }
        // Saat protection on, kita TUNGGU user klik tombol "Mulai"
        // (requestFullscreen butuh user gesture langsung dari klik).
    },

    _attachAlwaysOnHandlers() {
        document.addEventListener('contextmenu', (e) => { e.preventDefault(); this.logViolation('right_click'); });
        document.addEventListener('copy', (e) => { e.preventDefault(); this.logViolation('copy'); });
        document.addEventListener('paste', (e) => { e.preventDefault(); this.logViolation('paste'); });
        document.addEventListener('cut', (e) => { e.preventDefault(); this.logViolation('cut'); });
    },

    async startExam() {
        this.needStart = false;

        // WAJIB di sini: browser memblokir audio sampai ada gesture user, dan
        // klik tombol "Mulai" adalah satu-satunya gesture yang dijamin ada
        // sebelum pelanggaran pertama bisa terjadi.
        this._unlockAudio();

        // Kunci zoom LEBIH DULU, sebelum detektor apa pun dipasang. Zoom bukan
        // kecurangan, tapi efek sampingnya (angka viewport mengecil) memicu
        // detektor split-screen di mobile dan detektor DevTools di desktop.
        this._attachZoomLock();

        if (!this.isMobile) {
            // === DESKTOP: WAJIB FULLSCREEN ===
            // (Sempat dicoba juga di mobile atas permintaan, tapi di-revert lagi
            // atas permintaan user -- fullscreen HANYA di desktop, seperti semula.)
            // Guard `document.fullscreenEnabled` supaya browser yang memang tidak
            // mendukung Fullscreen API tidak ikut dicatat sebagai pelanggaran.
            if (document.fullscreenEnabled) {
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
            }
        } else {
            // === MOBILE: deteksi orientasi & rotasi yang aneh ===
            if (screen.orientation && screen.orientation.lock) {
                try { await screen.orientation.lock('portrait'); } catch (e) {}
            }
            screen.orientation?.addEventListener('change', () => {
                this.logViolation('orientation_change', screen.orientation?.type);
            });

            // Mobile: deteksi touch dengan multi-finger.
            // Ambang 3 (bukan 2): pinch-zoom pakai 2 jari, dan jempol yang
            // ikut menempel saat mencubit membuatnya terbaca 3 sentuhan --
            // itu siswa yang mau memperbesar soal, bukan menyontek.
            document.addEventListener('touchstart', (e) => {
                if (e.touches.length > 3) this.logViolation('multi_touch');
            }, { passive: true });

            // Mobile: deteksi layar terbelah (split-screen / multi-window Android)
            this._attachSplitScreenDetection();
        }

        this.attachCommonHandlers();
        this.onExamStarted?.();
    },

    /** Apakah halaman sedang dalam kondisi pinch-zoom (skala > 1). */
    _isZoomed() {
        return (window.visualViewport?.scale ?? 1) > 1.01;
    },

    /**
     * Kunci zoom (mobile & desktop).
     *
     * LATAR: meta viewport sudah memuat `user-scalable=no, maximum-scale=1`,
     * tapi iOS Safari (sejak iOS 10) dan Chrome Android dengan setelan
     * aksesibilitas "paksa aktifkan zoom" SENGAJA mengabaikan atribut itu --
     * jadi meta tag saja tidak cukup, zoom tetap bisa terjadi.
     *
     * Zoom sendiri bukan kecurangan, tapi mengubah angka viewport yang dipakai
     * detektor lain: di Chrome Android pinch-zoom mengecilkan
     * innerWidth/innerHeight (terbaca 'split_screen'), dan di desktop browser
     * zoom mengecilkan innerWidth sehingga selisih outerWidth-innerWidth
     * melar (terbaca 'devtools'). Siswa yang cuma memperbesar soal jadi kena
     * pelanggaran. Gesture-nya dicegat di sini, DAN kedua detektor tersebut
     * dibuat kebal zoom -- dua lapis, supaya browser yang tetap ngotot
     * mengizinkan zoom pun tidak menghasilkan pelanggaran palsu.
     *
     * Yang TIDAK dicegat: scroll satu jari, tap biasa, dan scroll roda mouse
     * tanpa Ctrl -- semuanya masih dibutuhkan untuk mengerjakan soal.
     */
    _attachZoomLock() {
        if (this.isMobile) {
            // iOS Safari: pinch memunculkan gesture* (bukan touchmove multi-jari).
            ['gesturestart', 'gesturechange', 'gestureend'].forEach((ev) => {
                document.addEventListener(ev, (e) => {
                    e.preventDefault();
                    this._hintZoomLocked();
                }, { passive: false });
            });

            // Android & umum: pinch = >1 jari bergerak bersamaan.
            // passive:false wajib, kalau tidak preventDefault() diabaikan browser.
            document.addEventListener('touchmove', (e) => {
                if (e.touches.length > 1) {
                    e.preventDefault();
                    this._hintZoomLocked();
                }
            }, { passive: false });

            // Double-tap zoom. Hanya dicegat kalau dua ketukan jatuh di titik
            // yang hampir sama -- kalau tidak, siswa yang cepat memilih opsi A
            // lalu B akan kehilangan ketukan keduanya.
            let lastTap = 0;
            let lastX = 0;
            let lastY = 0;
            document.addEventListener('touchend', (e) => {
                const t = e.changedTouches[0];
                if (!t) return;
                const now = Date.now();
                const samePlace = Math.abs(t.clientX - lastX) < 30 && Math.abs(t.clientY - lastY) < 30;
                if (now - lastTap < 300 && samePlace) {
                    e.preventDefault();
                    this._hintZoomLocked();
                }
                lastTap = now;
                lastX = t.clientX;
                lastY = t.clientY;
            }, { passive: false });

            return;
        }

        // === DESKTOP: Ctrl + roda mouse, dan Ctrl +/-/0 ===
        // Sengaja hanya dicegah + diberi info, TIDAK dicatat sebagai
        // pelanggaran (beda dengan daftar tombol terlarang di
        // attachCommonHandlers) -- memperbesar tulisan bukan menyontek.
        document.addEventListener('wheel', (e) => {
            if (e.ctrlKey) {
                e.preventDefault();
                this._hintZoomLocked();
            }
        }, { passive: false });

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && ['+', '-', '=', '_', '0'].includes(e.key)) {
                e.preventDefault();
                this._hintZoomLocked();
            }
        });
    },

    /**
     * Beri tahu siswa bahwa zoom dimatikan -- sekadar info, TIDAK menambah
     * hitungan pelanggaran. Tanpa ini layar terasa "macet" dan siswa mengira
     * aplikasinya rusak.
     */
    _hintZoomLocked() {
        if (this.zoomHint) return;
        this.zoomHint = true;
        clearTimeout(this._zoomHintTimer);
        this._zoomHintTimer = setTimeout(() => { this.zoomHint = false; }, 2500);
    },

    /**
     * Heuristik deteksi split-screen/multi-window di mobile (BUKAN API resmi --
     * tidak ada event browser khusus untuk ini). Caranya: rekam luas viewport
     * sebagai baseline saat ujian dimulai, lalu bandingkan setiap resize
     * berikutnya terhadap baseline itu (BUKAN terhadap screen.width x height
     * fisik -- sengaja begitu supaya heuristik ini tetap akurat walau mobile
     * TIDAK dalam mode fullscreen, karena address bar/nav bar browser sudah
     * otomatis "mengurangi" viewport dari ukuran layar fisik meski tidak sedang
     * di-split sama sekali). Kalau viewport tiba-tiba menyusut jauh dari
     * baseline TANPA disertai rotasi layar (orientationchange), itu indikasi
     * kuat aplikasi sedang di-split. Baseline dikalibrasi ulang setelah rotasi
     * selesai (karena lebar/tinggi memang tertukar saat rotasi). Di-debounce
     * supaya tidak spam tiap kali resize kecil terjadi, dan tidak dobel-lapor
     * selama masih dalam kondisi split yang sama.
     *
     * YANG DIUKUR ADALAH LAYOUT VIEWPORT (documentElement.clientWidth/Height),
     * bukan window.innerWidth/innerHeight. Ini penting: di Chrome Android
     * innerWidth/innerHeight mengikuti VISUAL viewport, sehingga pinch-zoom
     * membuat angkanya menyusut dan ujian siswa yang cuma memperbesar soal
     * dicatat sebagai 'split_screen'. Layout viewport tidak berubah saat zoom,
     * tapi tetap berubah saat jendela benar-benar dibelah -- persis yang kita
     * mau. Guard _isZoomed() dipasang sebagai lapis kedua.
     */
    _attachSplitScreenDetection() {
        const viewportArea = () =>
            document.documentElement.clientWidth * document.documentElement.clientHeight;

        let baselineArea = viewportArea();
        let recentOrientationChange = false;
        let inSplitScreen = false;
        let resizeTimer = null;

        screen.orientation?.addEventListener('change', () => {
            recentOrientationChange = true;
            setTimeout(() => {
                recentOrientationChange = false;
                // Kalibrasi ulang baseline setelah rotasi selesai & UI settle.
                baselineArea = viewportArea();
            }, 800);
        });

        window.addEventListener('resize', () => {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (recentOrientationChange) return;
                // Sedang di-zoom (browser mengabaikan kunci zoom kita) -> angka
                // viewport tidak bisa dipercaya, lewati saja daripada menuduh.
                if (this._isZoomed()) return;

                const ratio = viewportArea() / baselineArea;

                if (ratio < 0.85) {
                    if (!inSplitScreen) {
                        inSplitScreen = true;
                        this.logViolation('split_screen');
                    }
                } else {
                    inSplitScreen = false;
                }
            }, 300);
        });
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
                (e.ctrlKey && e.shiftKey && ['I', 'J', 'C', 'K'].includes(e.key.toUpperCase())) ||
                (e.ctrlKey && ['U', 'S', 'P', 'A'].includes(e.key.toUpperCase())) ||
                (e.metaKey && ['I', 'U', 'S', 'P'].includes(e.key.toUpperCase())); // Mac
            if (blocked) {
                e.preventDefault();
                this.logViolation('blocked_key', e.key);
            }
        });

        // 3. DevTools detection (heuristic)
        //
        // Selisih outer-inner dinormalkan dulu terhadap tingkat zoom browser.
        // Tanpa normalisasi, siswa yang memperbesar halaman (Ctrl +) membuat
        // innerWidth mengecil sehingga selisihnya ikut melar dan terbaca
        // seolah-olah DevTools terbuka. Patokannya devicePixelRatio saat ujian
        // dimulai: browser zoom mengubah dpr sebanding dengan mengecilnya
        // innerWidth, sedangkan DevTools yang di-dock TIDAK mengubah dpr --
        // jadi hanya DevTools yang tetap lolos ambang.
        const baseDpr = window.devicePixelRatio || 1;
        setInterval(() => {
            if (this.isMobile) return;
            const zoom = (window.devicePixelRatio || 1) / baseDpr;
            const w = window.outerWidth - window.innerWidth * zoom;
            const h = window.outerHeight - window.innerHeight * zoom;
            if (w > 200 || h > 200) {
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

    /* ================= ALARM SUARA PELANGGARAN =================
     * Toggle per-ujian (kolom quizzes.violation_sound_enabled). Sengaja TANPA
     * file audio: sirene dibangkitkan Web Audio API dan kalimat peringatan
     * diucapkan Text-to-Speech bawaan browser, jadi tidak ada aset yang perlu
     * di-deploy dan tidak ada request jaringan saat ujian berlangsung.
     */

    /**
     * Bangunkan AudioContext & TTS lewat gesture user (klik "Mulai").
     * Tanpa ini Chrome/Safari menolak memutar audio dengan autoplay policy.
     */
    _unlockAudio() {
        if (!this.soundEnabled) return;
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) {
                this._audioCtx = this._audioCtx || new Ctx();
                if (this._audioCtx.state === 'suspended') this._audioCtx.resume();
            }
            // Chrome hanya mengizinkan speak() setelah synthesis pernah
            // "disentuh" dalam konteks gesture user.
            window.speechSynthesis?.resume?.();
        } catch (e) { /* audio tidak tersedia → ujian tetap jalan */ }
    },

    /** Sirene keras + kalimat "Anda melakukan kecurangan". */
    _playViolationAlarm() {
        if (!this.soundEnabled) return;
        this._playSiren();
        this._speakWarning();
    },

    /**
     * Sirene naik-turun 3x (~1,1 detik) memakai oscillator.
     * Gelombang 'square' dipilih karena paling menusuk/terdengar keras pada
     * speaker kecil HP dibanding sine.
     */
    _playSiren() {
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            this._audioCtx = this._audioCtx || new Ctx();
            const ctx = this._audioCtx;
            if (ctx.state === 'suspended') ctx.resume();

            const now = ctx.currentTime;
            const gain = ctx.createGain();
            gain.connect(ctx.destination);
            // Ramp singkat dari nilai sangat kecil: exponentialRamp tidak boleh
            // dari 0, dan tanpa ramp akan terdengar "klik" di awal.
            gain.gain.setValueAtTime(0.0001, now);
            gain.gain.exponentialRampToValueAtTime(0.9, now + 0.04);

            const osc = ctx.createOscillator();
            osc.type = 'square';
            osc.connect(gain);

            let t = now;
            for (let i = 0; i < 3; i++) {
                osc.frequency.setValueAtTime(880, t);
                osc.frequency.linearRampToValueAtTime(1760, t + 0.18);
                osc.frequency.linearRampToValueAtTime(880, t + 0.36);
                t += 0.36;
            }
            gain.gain.setValueAtTime(0.9, t - 0.06);
            gain.gain.exponentialRampToValueAtTime(0.0001, t);

            osc.start(now);
            osc.stop(t + 0.02);
        } catch (e) { /* diabaikan — alarm tidak boleh menggagalkan ujian */ }
    },

    /** Ucapkan peringatan dalam Bahasa Indonesia (setelah sirene selesai). */
    _speakWarning() {
        try {
            const synth = window.speechSynthesis;
            if (!synth || typeof SpeechSynthesisUtterance === 'undefined') return;

            // Pelanggaran beruntun → jangan menumpuk antrean ucapan.
            synth.cancel();

            const u = new SpeechSynthesisUtterance('Anda melakukan kecurangan!');
            u.lang = 'id-ID';
            u.volume = 1;   // maksimum
            u.rate = 0.95;  // sedikit lebih lambat supaya jelas terdengar
            u.pitch = 1;

            const idVoice = synth.getVoices().find((v) => /^id/i.test(v.lang));
            if (idVoice) u.voice = idVoice;

            // Beri jeda ~1,1 dtk supaya tidak bertabrakan dengan sirene.
            setTimeout(() => { try { synth.speak(u); } catch (e) {} }, 1120);
        } catch (e) { /* diabaikan */ }
    },

    /** Pindah ke halaman blokir tanpa prompt browser */
    goToBlocked() {
        // pakai replace agar tidak bisa "back"
        window.location.replace(this.blockedUrl);
    },

    async logViolation(type, detail = null) {
        if (!this.protectionEnabled) return;
        if (this.savingViolation) return;

        this.violations++;
        this.lastViolation = type;
        this.showWarning = true;
        clearTimeout(this._warningTimer);
        this._warningTimer = setTimeout(() => { this.showWarning = false; }, 4000);

        // Bunyikan SEBELUM fetch supaya efeknya instan (tidak menunggu server).
        this._playViolationAlarm();

        this.onViolationsChanged?.(this.violations);

        this.savingViolation = true;
        try {
            const r = await fetch(this.violationUrl, {
                method: 'POST',
                headers: this._headers(),
                body: JSON.stringify({ type, detail }),
            });

            // Sesi perangkat ini sudah tidak berlaku (409 = ditendang
            // SingleSessionGuard karena akun login di perangkat lain, 401/419 =
            // sesi/token sudah hangus). Serahkan ke halaman ujian untuk
            // memunculkan alert & keluar -- jangan diproses seperti balasan
            // pelanggaran biasa (body-nya memang bukan format itu).
            if ([409, 401, 419].includes(r.status)) {
                this.onSessionConflict?.(r.status);
                return;
            }

            const data = await r.json();
            if (data.blocked) {
                // Mode "blokir" -> halaman blokir
                this.goToBlocked();
            } else if (data.logout) {
                // Mode "logout_otomatis" -> langsung ke halaman hasil (sudah di-submit di server)
                window.location.replace(window.location.pathname.replace(/\/[^\/]+$/, '') + '/result');
            }
        } catch (e) {
            console.error(e);
        } finally {
            this.savingViolation = false;
        }
    },

    _headers() {
        return {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Accept': 'application/json',
        };
    },
});
