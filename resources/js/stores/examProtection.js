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

    /** Info (BUKAN pelanggaran): siswa mencoba pinch-zoom yang sedang dikunci. */
    zoomHint: false,

    // ---- config, di-set sekali lewat init() ----
    quizName: '',
    maxViolations: 0,
    protectionEnabled: false,
    soundEnabled: false,
    violationUrl: '',
    blockedUrl: '',
    /** proteksi_mode aktif (mis. 'blokir', 'pengurangan_nilai') — dipakai
     *  komponen tampilan (ExamRulesCard, ExamViolationBanner) supaya pesan
     *  peringatannya sesuai konsekuensi mode ini, bukan selalu "diblokir". */
    proteksiMode: '',
    /** Poin nilai yang dipotong tiap pelanggaran, hanya relevan saat proteksiMode === 'pengurangan_nilai'. */
    nilaiPengurangan: 0,

    // ---- callback hook, di-set oleh Alpine (cbtExam) di show.blade.php ----
    onExamStarted: null,
    onViolationsChanged: null,
    /** Dipanggil saat server membalas 409: akun login di perangkat lain. */
    onSessionConflict: null,

    _warningTimer: null,
    _zoomHintTimer: null,
    _initialized: false,
    _audioCtx: null,

    /* ================= ANTRIAN PELANGGARAN OFFLINE-SAFE =================
     * LATAR: sebelumnya logViolation() cuma fetch() sekali -- kalau gagal
     * (jaringan putus, atau siswa sengaja mematikan WiFi CBT lalu pindah ke
     * paket data), laporannya HILANG PERMANEN tanpa retry, dan hitungan
     * pelanggaran di server tidak pernah bertambah -- itulah sebabnya mode
     * "blokir"/"logout_otomatis" terasa "tidak berfungsi" saat siswa keluar
     * tab lalu memutus jaringan. Sekarang tiap laporan yang gagal terkirim
     * disimpan di sessionStorage (per attempt, lewat violationUrl sbg key)
     * dan dicoba ulang otomatis saat: jaringan kembali (event 'online'),
     * tab kembali terlihat, dan lewat interval berkala -- jadi begitu siswa
     * kembali online, seluruh pelanggaran yang tertunda langsung dievaluasi
     * server dan blokir/logout tetap terjadi walau terlambat.
     */
    _pendingQueue: [],
    _flushing: false,
    _flushIntervalTimer: null,

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
        this.proteksiMode = config.proteksiMode || '';
        this.nilaiPengurangan = config.nilaiPengurangan || 0;

        this.isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)
                        || (window.matchMedia && window.matchMedia('(pointer:coarse)').matches);

        // Muat antrean pelanggaran yang gagal terkirim sebelumnya (mis. tab
        // di-reload saat masih offline) supaya tidak hilang begitu saja, lalu
        // pasang percobaan-ulang otomatis: saat jaringan kembali ('online'),
        // saat tab kembali terlihat, DAN lewat interval berkala (jaga-jaga
        // browser tidak selalu memicu event 'online' dengan andal di mobile).
        if (this.protectionEnabled) {
            this._loadQueue();
            window.addEventListener('online', () => this._flushQueue());
            this._flushIntervalTimer = setInterval(() => this._flushQueue(), 15000);
            if (this._pendingQueue.length) this._flushQueue();
        }

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
        // 'contextmenu' awalnya dibuat untuk memblokir klik-kanan DESKTOP (mis.
        // percobaan buka "Inspect Element"). Masalahnya, di mobile, LONG-PRESS
        // biasa pada teks/gambar APAPUN memicu event 'contextmenu' yang PERSIS
        // SAMA -- jadi setiap kali siswa menahan layar (mis. sekadar mencoba
        // menyeleksi teks untuk dibaca ulang, atau tidak sengaja menekan agak
        // lama), tercatat sebagai "percobaan klik-kanan" walau di HP tidak
        // mungkin membuka DevTools lewat long-press sama sekali -- salah
        // menilai niat, bukan proteksi yang sungguh relevan di mobile. Menu-nya
        // tetap dicegah muncul (preventDefault) di kedua platform, tapi
        // pelanggarannya HANYA dicatat di desktop.
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            if (! this.isMobile) this.logViolation('right_click');
        });
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
        //
        // MOBILE diberi toleransi (grace period) sebelum dicatat sebagai
        // pelanggaran -- layar terkunci sebentar, notifikasi masuk, telepon
        // masuk, atau sekadar OS menggeser fokus adalah kejadian NORMAL & SERING
        // di HP, bukan indikasi mencontek. Tanpa toleransi ini, satu kejadian
        // layar terkunci ~1 detik bisa memicu blur + visibilitychange + pageshow
        // (persisted) SEKALIGUS -- 2-3 "pelanggaran" dari SATU momen yang sama
        // sekali -- dan dengan ambang batas default cuma 5, siswa yang wajar
        // saja (mis. sedang mengecek ulang jawaban lalu layarnya sempat mati)
        // bisa gampang ke-auto-submit/diblokir tanpa benar-benar berbuat curang.
        // Pelanggaran BARU dicatat kalau halaman masih tersembunyi setelah
        // toleransi waktu ini lewat -- itu baru indikasi kuat siswa benar-benar
        // pindah aplikasi/keluar dalam waktu berarti, bukan sekadar dilirik.
        //
        // DESKTOP tidak diberi grace period (perilaku lama dipertahankan persis)
        // -- pindah tab/window di desktop adalah gestur yang jauh lebih sengaja.
        const MOBILE_HIDDEN_GRACE_MS = 5000;
        let hiddenTimer = null;

        // Pelanggaran ESKALASI: sebelumnya "keluar tab/app" cuma dicatat SEKALI
        // per kunjungan keluar, berapa pun lama siswa pergi -- artinya siswa
        // yang sengaja keluar tab lalu pergi berselancar (mis. ganti ke paket
        // data) selama bermenit-menit tetap cuma kena 1 pelanggaran, jauh di
        // bawah ambang batas (default 5) yang memicu blokir/logout. Sekarang,
        // SELAMA halaman masih tersembunyi, pelanggaran jenis yang sama dicatat
        // ulang tiap HIDDEN_ESCALATION_MS -- absen sebentar (dilirik notifikasi)
        // tetap cuma 1 pelanggaran, tapi absen lama otomatis menumpuk sampai
        // menembus ambang begitu koneksi/tab kembali. Catatan: timer di tab
        // background BISA di-throttle browser (Chrome menahannya jadi ~1x/menit
        // setelah beberapa saat) -- tetap jauh lebih baik daripada tidak pernah
        // terdeteksi sama sekali.
        const HIDDEN_ESCALATION_MS = 20000;
        let escalationTimer = null;
        const startEscalation = (type) => {
            clearInterval(escalationTimer);
            escalationTimer = setInterval(() => {
                if (document.hidden) this.logViolation(type);
            }, HIDDEN_ESCALATION_MS);
        };
        const stopEscalation = () => { clearInterval(escalationTimer); escalationTimer = null; };

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (this.isMobile) {
                    clearTimeout(hiddenTimer);
                    hiddenTimer = setTimeout(() => {
                        if (document.hidden) {
                            this.logViolation('app_switch');
                            startEscalation('app_switch');
                        }
                    }, MOBILE_HIDDEN_GRACE_MS);
                } else {
                    this.logViolation('tab_switch');
                    startEscalation('tab_switch');
                }
            } else {
                clearTimeout(hiddenTimer);
                stopEscalation();
                // Siswa baru kembali -- coba kirim ulang segera pelanggaran yang
                // tertunda (mis. gagal terkirim saat jaringan sempat putus),
                // jangan tunggu interval berkala berikutnya.
                this._flushQueue();
            }
        });
        window.addEventListener('blur', () => {
            // Mobile: blur nyaris selalu berbarengan dengan visibilitychange di
            // atas (layar kunci, pindah app, notifikasi) -- sudah ditangani lewat
            // grace period, jadi TIDAK dicatat dobel di sini.
            if (!this.isMobile) this.logViolation('window_blur');
        });
        window.addEventListener('pageshow', (e) => {
            // Sama alasannya: di mobile, restore dari bfcache adalah efek
            // SAMPING dari app-switch yang sama yang sudah (atau belum, kalau
            // masih dalam toleransi) tercatat lewat visibilitychange di atas.
            if (e.persisted && !this.isMobile) this.logViolation('back_forward_cache');
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

            // Tombol Print Screen: TIDAK BISA dicegah (OS Windows sudah
            // menyalin layar ke clipboard sebelum event ini sampai ke
            // browser), jadi ini murni PENCATATAN sebagai jejak/deteren --
            // bukan pencegahan. Kombinasi screenshot lain (Win+Shift+S,
            // Cmd+Shift+3/4 di Mac, tombol Power+Volume di HP) tidak pernah
            // sampai ke browser sama sekali -- tidak ada API web untuk itu,
            // jadi TIDAK bisa dideteksi walau dicoba. Jangan janjikan lebih
            // dari yang benar-benar sanggup dideteksi di sini.
            if (e.key === 'PrintScreen') {
                this.logViolation('screenshot_attempt', 'PrintScreen');
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

        this.violations++;
        this.lastViolation = type;
        this.showWarning = true;
        clearTimeout(this._warningTimer);
        this._warningTimer = setTimeout(() => { this.showWarning = false; }, 4000);

        // Bunyikan SEBELUM fetch supaya efeknya instan (tidak menunggu server).
        this._playViolationAlarm();

        this.onViolationsChanged?.(this.violations);

        this._deliverViolation({ type, detail, ts: Date.now() });
    },

    /**
     * Kirim SATU laporan pelanggaran, dengan jalur berbeda tergantung apakah
     * halaman sedang terlihat atau tersembunyi:
     *
     * - Tersembunyi (siswa sudah pindah tab/app) → pakai navigator.sendBeacon,
     *   BUKAN fetch. fetch() dari tab yang sedang di-background/ditutup bisa
     *   dibatalkan begitu saja oleh browser sebelum sempat terkirim; sendBeacon
     *   memang dirancang browser supaya tetap coba terkirim walau halamannya
     *   sedang tidak aktif. sendBeacon tidak bisa membaca balasan server (jadi
     *   blokir/logout baru ketahuan lewat heartbeat ping() berikutnya atau saat
     *   antrean di-flush), dan tidak mendukung header custom -- makanya CSRF
     *   token dikirim sebagai field form '_token', bukan header X-CSRF-TOKEN.
     * - Terlihat → fetch() seperti biasa, bisa langsung baca balasan (blocked/
     *   logout/409 dst).
     *
     * Kapan pun gagal terkirim (offline, sendBeacon ditolak, exception apa
     * pun), laporan masuk antrean (_enqueue) untuk dicoba ulang nanti --
     * TIDAK PERNAH dibuang diam-diam seperti sebelumnya.
     */
    async _deliverViolation(payload) {
        // Jaga urutan: coba habiskan antrean lama dulu sebelum laporan baru,
        // supaya server menerima kronologi pelanggaran apa adanya.
        if (this._pendingQueue.length) await this._flushQueue();

        if (document.visibilityState === 'hidden' && navigator.sendBeacon) {
            if (this._sendBeacon(payload)) return;
            this._enqueue(payload);
            return;
        }

        const ok = await this._postViolation(payload);
        if (!ok) this._enqueue(payload);
    },

    /**
     * POST satu laporan lewat fetch. Return true kalau server sudah
     * menerima & membalas (apa pun isi balasannya) -- false kalau perlu
     * dicoba ulang (jaringan putus/exception). Balasan 409/401/419/redirect
     * (sesi mati) DIANGGAP "diterima" (bukan gagal jaringan) supaya tidak
     * mengantre selamanya untuk sesi yang memang sudah tidak berlaku.
     */
    async _postViolation(payload) {
        try {
            const r = await fetch(this.violationUrl, {
                method: 'POST',
                headers: this._headers(),
                body: JSON.stringify({ type: payload.type, detail: payload.detail }),
            });

            // Sesi perangkat ini sudah tidak berlaku (409 = ditendang
            // SingleSessionGuard karena akun login di perangkat lain, 401/419 =
            // sesi/token sudah hangus, r.redirected = middleware auth diam-diam
            // mengarahkan ke /login dan fetch mengikutinya -- tanpa cek ini,
            // status akhirnya 200 dan kode di bawah salah mengira laporan
            // pelanggaran berhasil tersimpan). Serahkan ke halaman ujian untuk
            // memunculkan alert & keluar -- jangan diproses seperti balasan
            // pelanggaran biasa (body-nya memang bukan format itu).
            if ([409, 401, 419].includes(r.status) || r.redirected) {
                this.onSessionConflict?.(r.status, r.redirected);
                return true;
            }

            const data = await r.json();
            if (data.blocked) {
                this.goToBlocked();
            } else if (data.logout) {
                window.location.replace(window.location.pathname.replace(/\/[^\/]+$/, '') + '/result');
            }
            return true;
        } catch (e) {
            return false;
        }
    },

    /** Kirim lewat sendBeacon (dipakai saat halaman tersembunyi). Return false kalau browser menolak antre-kannya. */
    _sendBeacon(payload) {
        try {
            const tokenEl = document.querySelector('meta[name=csrf-token]');
            if (!tokenEl) return false;
            const fd = new FormData();
            fd.append('type', payload.type);
            if (payload.detail) fd.append('detail', payload.detail);
            fd.append('_token', tokenEl.content);
            return navigator.sendBeacon(this.violationUrl, fd);
        } catch (e) {
            return false;
        }
    },

    _queueStorageKey() {
        return 'examViolationQueue:' + this.violationUrl;
    },

    _loadQueue() {
        try {
            const raw = sessionStorage.getItem(this._queueStorageKey());
            this._pendingQueue = raw ? JSON.parse(raw) : [];
        } catch (e) {
            this._pendingQueue = [];
        }
    },

    _saveQueue() {
        try {
            sessionStorage.setItem(this._queueStorageKey(), JSON.stringify(this._pendingQueue));
        } catch (e) { /* storage penuh/diblokir -- antrean tetap jalan di memori */ }
    },

    _enqueue(payload) {
        this._pendingQueue.push(payload);
        this._saveQueue();
    },

    /**
     * Kosongkan antrean SECARA BERURUTAN (bukan paralel) -- kalau laporan
     * pertama masih gagal (masih offline), berhenti & simpan sisanya untuk
     * percobaan berikutnya, jangan lompat ke laporan setelahnya (urutan
     * kronologis pelanggaran penting untuk audit di Monitoring Ujian).
     */
    async _flushQueue() {
        if (this._flushing || this._pendingQueue.length === 0) return;
        this._flushing = true;
        try {
            while (this._pendingQueue.length > 0) {
                const ok = await this._postViolation(this._pendingQueue[0]);
                if (!ok) break;
                this._pendingQueue.shift();
                this._saveQueue();
            }
        } finally {
            this._flushing = false;
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
