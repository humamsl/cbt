{{--
    Render rumus matematika LaTeX (KaTeX) — self-hosted di public/vendor/katex,
    tanpa CDN / internet. Lihat scripts/copy-katex.js.

    Kenapa perlu: soal matematika yang di-copy dari ChatGPT/Gemini/Wikipedia
    masuk ke editor sebagai teks LaTeX mentah, mis. "\(\sqrt{144} = 12\)".
    Teks itu DISIMPAN apa adanya di database (aman diedit ulang), lalu
    di-render jadi rumus asli di setiap tempat soal ditampilkan.

    Cara pakai: beri class "soal-math" pada elemen pembungkus isi soal/opsi,
    lalu (untuk konten yang dimuat via AJAX) panggil window.renderSoalMath(el).
--}}
<link rel="stylesheet" href="{{ asset('vendor/katex/katex.min.css') }}">
<script defer src="{{ asset('vendor/katex/katex.min.js') }}"></script>
<script defer src="{{ asset('vendor/katex/contrib/auto-render.min.js') }}"></script>
<script>
// Delimiter sengaja TANPA "$...$" tunggal — teks harga seperti "$5 dan $10"
// akan salah dianggap rumus.
window.SOAL_MATH_OPTIONS = {
    delimiters: [
        { left: '$$',       right: '$$',      display: true  },
        { left: '\\[',      right: '\\]',     display: true  },
        { left: '\\(',      right: '\\)',     display: false },
        { left: '\\begin{equation}', right: '\\end{equation}', display: true },
        { left: '\\begin{align}',    right: '\\end{align}',    display: true },
    ],
    ignoredTags: ['script', 'noscript', 'style', 'textarea', 'pre', 'code', 'option'],
    throwOnError: false,   // LaTeX salah tampil merah, tidak mematahkan halaman
    errorColor: '#e11d48',
    strict: false,
};

/**
 * Render LaTeX di dalam `root` (default: seluruh halaman).
 * Aman dipanggil berkali-kali — konten yang sudah ter-render tidak punya
 * delimiter lagi sehingga dilewati.
 */
window.renderSoalMath = function (root) {
    if (typeof window.renderMathInElement !== 'function') return;

    const scope = root || document;
    const targets = [];
    if (scope.classList && scope.classList.contains('soal-math')) targets.push(scope);
    if (scope.querySelectorAll) targets.push(...scope.querySelectorAll('.soal-math'));

    targets.forEach((el) => {
        try {
            window.renderMathInElement(el, window.SOAL_MATH_OPTIONS);
        } catch (e) {
            console.warn('KaTeX gagal render:', e);
        }
    });
};

document.addEventListener('DOMContentLoaded', () => window.renderSoalMath());
</script>
