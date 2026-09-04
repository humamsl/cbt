<?php

namespace App\Services\Soal;

use App\Models\Question;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportSoalService
{
    /** Generate file Word (.docx) yang ber-format kompatibel dengan importer */
    public function exportWord(Collection $questions, string $title = 'Bank Soal'): StreamedResponse
    {
        // WAJIB: default PhpWord TIDAK meng-escape teks ke XML. Soal/opsi yang
        // mengandung HTML mentah (mis. "<img src=...>" atau "<sup>3</sup>" —
        // sangat umum di soal matematika) akan menyusup ke document.xml dan
        // membuat file .docx KORUP (Word menolak membukanya sama sekali).
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addTitle($title, 1);
        $section->addText('Dicetak: '.now()->translatedFormat('d F Y H:i'), ['italic' => true]);
        $section->addTextBreak(1);

        foreach ($questions as $idx => $q) {
            $jenis = $this->typeSlug($q);
            $section->addText('#JENIS: '.$jenis, ['bold' => true]);
            if ($q->mapel) $section->addText('#MAPEL: '.$q->mapel->kode_mapel);
            if ($q->tingkat) $section->addText('#TINGKAT: '.$q->tingkat);
            if ($q->topic) $section->addText('#TOPIK: '.$this->plainText($q->topic->topic));
            $section->addText('#JUDUL: '.$this->plainText($q->title));
            $section->addText('#SOAL: '.$this->plainText($q->question));
            $this->writeWordImages($section, $q->question);

            $this->writeWordOptions($section, $q, $jenis);

            $section->addText('---');
            $section->addTextBreak(1);
        }

        $filename = $this->slug($title).'-'.date('Ymd-His').'.docx';
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /** Generate PDF via dompdf — view 'cbt.bank-soal.export-pdf' */
    public function exportPdf(Collection $questions, string $title = 'Bank Soal', bool $withAnswer = false)
    {
        // load() hanya ada di Eloquent\Collection. Caller WAJIB sudah eager-load relasi
        // sebelum memanggil method ini (lihat TesController::questionsFromQuiz).
        if ($questions instanceof \Illuminate\Database\Eloquent\Collection) {
            $questions->loadMissing('options', 'mapel', 'type');
        }

        $pdf = Pdf::loadView('cbt.bank-soal.export-pdf', [
            'questions' => $questions,
            'title' => $title,
            'withAnswer' => $withAnswer,
        ])->setPaper('a4', 'portrait');

        // Gambar soal di-embed sebagai data URI (lihat pdfHtml()). Default
        // config barryvdh/laravel-dompdf hanya mengizinkan file/http/https —
        // tanpa "data://" gambar dirender sebagai kotak rusak.
        $pdf->getDomPDF()->getOptions()->addAllowedProtocol('data://');

        $filename = $this->slug($title).'-'.date('Ymd-His').'.pdf';
        return $pdf->download($filename);
    }

    /**
     * Generate template Word (.docx) berisi 5 contoh soal (satu per jenis)
     * dengan format yang KOMPATIBEL dengan importer Word.
     */
    public function templateWord(): StreamedResponse
    {
        // WAJIB: sama seperti exportWord() -- tanpa ini, karakter XML spesial
        // (&, <, >) di teks apa pun di bawah (mis. "Bilangan Berpangkat & Akar")
        // ditulis mentah ke document.xml dan membuat file .docx KORUP (Word
        // menolak membukanya / minta "repair"). Baru ketahuan karena template
        // ini plain text-only sehingga sebelumnya dikira aman tanpa escaping.
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Header dokumen
        $section->addTitle('Template Import Bank Soal', 1);
        $section->addText('Hapus baris ini sebelum import. Setiap soal harus berakhir dengan "---".',
            ['italic' => true, 'color' => '7A7A7A']);
        $section->addTextBreak(1);

        // ===== Contoh 1: PG (Pilihan Ganda) =====
        $section->addText('#JENIS: pg', ['bold' => true]);
        $section->addText('#MAPEL: MTK');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Bilangan Berpangkat & Akar');
        $section->addText('#JUDUL: Akar 144');
        $section->addText('#SOAL: Berapa akar dari 144?');
        $section->addText('A. 10');
        $section->addText('B. 11');
        $section->addText('C. 12');
        $section->addText('D. 13');
        $section->addText('#JAWABAN: C');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 2: PGK (Pilihan Ganda Kompleks) =====
        $section->addText('#JENIS: pgk', ['bold' => true]);
        $section->addText('#MAPEL: MTK');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Bilangan Prima');
        $section->addText('#JUDUL: Bilangan Prima');
        $section->addText('#SOAL: Manakah yang termasuk bilangan prima?');
        $section->addText('A. 2');
        $section->addText('B. 4');
        $section->addText('C. 7');
        $section->addText('D. 9');
        $section->addText('E. 11');
        $section->addText('#JAWABAN: A,C,E');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 3: Fill the Blank =====
        $section->addText('#JENIS: fill-blank', ['bold' => true]);
        $section->addText('#MAPEL: BIN');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Geografi Indonesia');
        $section->addText('#JUDUL: Ibu Kota Indonesia');
        $section->addText('#SOAL: Ibu kota negara Indonesia adalah ___');
        $section->addText('#JAWABAN: Jakarta');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 4: Benar / Salah =====
        $section->addText('#JENIS: benar-salah', ['bold' => true]);
        $section->addText('#MAPEL: IPS');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Bentuk Bumi');
        $section->addText('#JUDUL: Bumi Bulat');
        $section->addText('#SOAL: Bumi berbentuk bulat sempurna.');
        $section->addText('#JAWABAN: S');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 5: Penjodohan =====
        $section->addText('#JENIS: penjodohan', ['bold' => true]);
        $section->addText('#MAPEL: IPA');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Organ Tubuh Manusia');
        $section->addText('#JUDUL: Pasangkan Organ');
        $section->addText('#SOAL: Pasangkan organ dengan fungsinya');
        $section->addText('A. Jantung');
        $section->addText('B. Paru-paru');
        $section->addText('C. Hati');
        $section->addText('#JAWABAN: A=Memompa darah; B=Pernapasan; C=Detoksifikasi');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 6: PG dengan GAMBAR =====
        // Tempel/insert gambar tepat SETELAH baris #SOAL (sebelum opsi) supaya
        // ikut ke soal. Untuk gambar pada opsi, taruh gambar setelah baris opsi.
        $section->addText('#JENIS: pg', ['bold' => true]);
        $section->addText('#MAPEL: MTK');
        $section->addText('#TINGKAT: 10');
        $section->addText('#TOPIK: Bangun Datar');
        $section->addText('#JUDUL: Luas Persegi');
        $section->addText('#SOAL: Perhatikan gambar berikut. Berapakah luas persegi tersebut?');
        if ($png = $this->placeholderImagePng('CONTOH GAMBAR SOAL')) {
            // Gambar contoh — ganti dengan gambar soal Anda saat mengisi template.
            $section->addImage($png, ['width' => 190, 'height' => 77]);
        }
        $section->addText('A. 16 cm2');
        $section->addText('B. 20 cm2');
        $section->addText('C. 24 cm2');
        $section->addText('D. 32 cm2');
        $section->addText('#JAWABAN: A');
        $section->addText('---');
        $section->addTextBreak(1);

        // Catatan
        $section->addTextBreak(1);
        $section->addText('Catatan format:', ['bold' => true]);
        $section->addListItem('Setiap soal diawali #JENIS dan ditutup baris "---".');
        $section->addListItem('Kode jenis: pg, pgk, fill-blank, benar-salah, penjodohan.');
        $section->addListItem('Kode #MAPEL harus sama dengan kode mapel di Data Center.');
        $section->addListItem('Tingkat opsional (1–12). Boleh dikosongkan.');
        $section->addListItem('#TOPIK wajib diisi. Kalau nama topiknya belum ada untuk mapel+tingkat '
            .'tsb, akan otomatis dibuatkan sesuai nama yang ditulis.');
        $section->addListItem('Opsi jawaban PG/PGK: minimal A-D, opsi E opsional -- tulis baris '
            .'"E. ..." hanya kalau soalnya memang butuh 5 pilihan, kalau tidak cukup A-D saja.');
        $section->addListItem('Jawaban PG = huruf tunggal (A/B/C/D/E).');
        $section->addListItem('Jawaban PGK = huruf dipisah koma (mis. A,C,E).');
        $section->addListItem('Jawaban Benar-Salah = B (benar) atau S (salah).');
        $section->addListItem('Jawaban Penjodohan = "huruf=teks" dipisah titik koma.');
        $section->addListItem('Gambar: tempel/insert gambar setelah baris #SOAL agar ikut ke soal, '
            .'atau setelah baris opsi (A./B./...) agar ikut ke opsi tsb.');

        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-soal.docx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
    }

    /** Generate template Excel kosong untuk download */
    public function templateExcel(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Bank Soal');

        $headers = [
            'jenis', 'mapel_kode', 'tingkat', 'topik',
            'judul', 'pertanyaan',
            'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
            'jawaban', 'gambar',
        ];
        $lastCol = chr(64 + count($headers)); // 13 kolom -> 'M'
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A1:{$lastCol}1")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Paksa kolom data jadi TEXT supaya Excel tidak auto-convert
        // (mis. kode mapel "7-1", jawaban "A,C,E", angka tetap text)
        $sheet->getStyle("A2:{$lastCol}9999")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Contoh baris untuk tiap jenis. Kolom "topik" WAJIB diisi (nama topik
        // yang sudah ada di menu Topik untuk mapel+tingkat tsb — kalau belum
        // ada, akan otomatis dibuatkan sesuai nama yang ditulis di sini).
        // Kolom terakhir = gambar (URL / data-URI, opsional). Contoh URL
        // diberikan pada baris PG untuk menunjukkan formatnya.
        $examples = [
            ['pg',          'MTK', '10', 'Bilangan Berpangkat & Akar', 'Akar 144',            'Berapa akar dari 144?',                '10','11','12','13','',           'C',      'https://situs-anda.com/gambar/soal.png'],
            ['pgk',         'MTK', '10', 'Bilangan Prima',             'Bilangan prima',      'Manakah bilangan prima?',              '2','4','7','9','11',             'A,C,E',  ''],
            ['fill-blank',  'BIN', '10', 'Geografi Indonesia',         'Ibu kota Indonesia',  'Ibu kota negara Indonesia adalah ___', '','','','','',                   'Jakarta',''],
            ['benar-salah', 'IPS', '10', 'Bentuk Bumi',                'Bumi bulat',          'Bumi berbentuk bulat sempurna.',       '','','','','',                   'S',      ''],
            ['penjodohan',  'IPA', '10', 'Organ Tubuh Manusia',        'Pasangkan organ',     'Pasangkan organ dengan fungsinya',     'Jantung','Paru-paru','Hati','','', 'A=Memompa darah; B=Pernapasan; C=Detoksifikasi', ''],
        ];
        foreach ($examples as $rIdx => $row) {
            $r = 2 + $rIdx;
            $cIdx = 0;
            foreach ($row as $value) {
                $col = chr(65 + $cIdx);
                $sheet->setCellValueExplicit("{$col}{$r}", (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }

        foreach (range('A', $lastCol) as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $this->addExcelPetunjukSheet($spreadsheet);
        // Pastikan file terbuka pada sheet data (bukan "Petunjuk") saat dibuka
        // user, dan importer membaca sheet yang benar.
        $spreadsheet->setActiveSheetIndex(0);

        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Sheet kedua "Petunjuk": penjelasan format termasuk cara menambah gambar. */
    protected function addExcelPetunjukSheet(Spreadsheet $spreadsheet): void
    {
        $ws = $spreadsheet->createSheet();
        $ws->setTitle('Petunjuk');

        $lines = [
            ['PETUNJUK PENGISIAN TEMPLATE IMPORT BANK SOAL', true],
            ['', false],
            ['Isi data pada sheet "Bank Soal". Baris 1 adalah header (jangan diubah/dihapus).', false],
            ['Hapus baris contoh sebelum mengimport data Anda sendiri.', false],
            ['', false],
            ['Kolom jenis: pg, pgk, fill-blank, benar-salah, penjodohan.', false],
            ['Kolom mapel_kode: harus sama dengan kode mapel di Data Center.', false],
            ['Kolom tingkat: 1-12, opsional (boleh dikosongkan).', false],
            ['Kolom topik: WAJIB diisi. Kalau nama topiknya belum ada di menu Topik', false],
            ['   untuk mapel+tingkat tsb, akan otomatis dibuatkan sesuai nama yang ditulis.', false],
            ['Kolom opsi_e: opsional. Kosongkan kalau soal PG/PGK cukup 4 opsi (A-D) seperti biasa;', false],
            ['   isi kalau memang butuh opsi ke-5.', false],
            ['Jawaban PG = huruf tunggal (mis. C). PGK = huruf dipisah koma (mis. A,C,E).', false],
            ['Jawaban Benar-Salah = B atau S. Fill-blank = teks jawaban.', false],
            ['Jawaban Penjodohan = "huruf=teks" dipisah titik koma (mis. A=..; B=..).', false],
            ['', false],
            ['MENAMBAH GAMBAR PADA SOAL', true],
            ['1. Kolom "gambar": isi dengan URL gambar (mis. https://.../soal.png)', false],
            ['   atau data-URI base64 (mis. data:image/png;base64,....).', false],
            ['   Gambar akan diunduh & disimpan otomatis ke server saat import.', false],
            ['2. Atau tempel/insert gambar langsung ke dalam sheet (Insert > Picture),', false],
            ['   letakkan gambar pada BARIS soal yang bersangkutan. Gambar akan', false],
            ['   ditautkan ke soal pada baris tempat gambar itu berada.', false],
            ['3. Bisa juga menulis tag <img src="..."> langsung di kolom pertanyaan/opsi.', false],
            ['', false],
            ['Untuk soal yang banyak gambarnya, Template Word (.docx) lebih mudah:', false],
            ['cukup tempel gambar tepat di bawah baris #SOAL atau baris opsi.', false],
        ];

        foreach ($lines as $i => [$text, $bold]) {
            $cell = 'A'.($i + 1);
            $ws->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
            if ($bold) $ws->getStyle($cell)->getFont()->setBold(true);
        }
        $ws->getColumnDimension('A')->setWidth(90);
    }

    /*    helpers    */
    protected function writeWordOptions($section, Question $q, string $jenis): void
    {
        switch ($jenis) {
            case 'pg':
            case 'pgk':
                $letter = 'A';
                $correct = [];
                foreach ($q->options as $opt) {
                    $section->addText("$letter. ".$this->plainText($opt->option_text));
                    $this->writeWordImages($section, $opt->option_text);
                    if ($opt->is_correct) $correct[] = $letter;
                    $letter++;
                }
                $section->addText('#JAWABAN: '.implode(',', $correct));
                break;

            case 'benar-salah':
                foreach ($q->options as $opt) {
                    if ($opt->is_correct) {
                        $section->addText('#JAWABAN: '.(strtoupper(substr($this->plainText($opt->option_text), 0, 1)) === 'B' ? 'B' : 'S'));
                        break;
                    }
                }
                break;

            case 'fill-blank':
                $section->addText('#JAWABAN: '.$this->plainText($q->correct_answer_text ?? ''));
                break;

            case 'penjodohan':
                $kiri = $q->options->where('is_left_side', true)->sortBy('order')->values();
                $kanan = $q->options->where('is_left_side', false)->keyBy('pair_group');
                $letter = 'A'; $pairs = [];
                foreach ($kiri as $opt) {
                    $section->addText("$letter. ".$this->plainText($opt->option_text));
                    if (isset($kanan[$opt->pair_group])) {
                        $pairs[] = $letter.'='.$this->plainText($kanan[$opt->pair_group]->option_text);
                    }
                    $letter++;
                }
                $section->addText('#JAWABAN: '.implode('; ', $pairs));
                break;
        }
    }

    /**
     * HTML soal → teks polos yang aman untuk PhpWord:
     *  - <sup>/<sub> angka diubah ke karakter Unicode (m<sup>3</sup> → m³)
     *    supaya makna matematisnya tidak hilang saat tag dibuang
     *  - entity (&radic; dsb) di-decode ke karakter aslinya
     *  - karakter kontrol ilegal XML dibuang (sisa paste dari MS Word —
     *    penyebab .docx korup selain HTML mentah)
     */
    protected function plainText(?string $html): string
    {
        if ($html === null || $html === '') return '';

        $sup = ['0'=>'⁰','1'=>'¹','2'=>'²','3'=>'³','4'=>'⁴','5'=>'⁵','6'=>'⁶','7'=>'⁷','8'=>'⁸','9'=>'⁹','+'=>'⁺','-'=>'⁻','n'=>'ⁿ'];
        $sub = ['0'=>'₀','1'=>'₁','2'=>'₂','3'=>'₃','4'=>'₄','5'=>'₅','6'=>'₆','7'=>'₇','8'=>'₈','9'=>'₉','+'=>'₊','-'=>'₋'];
        $html = preg_replace_callback('~<sup[^>]*>([^<]*)</sup>~i',
            fn ($m) => strtr($m[1], $sup), $html);
        $html = preg_replace_callback('~<sub[^>]*>([^<]*)</sub>~i',
            fn ($m) => strtr($m[1], $sub), $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Buang karakter kontrol yang ilegal di XML 1.0 (kecuali \t \n \r)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', $text);

        return trim(preg_replace('/[ \t]+/u', ' ', $text));
    }

    /** Sisipkan gambar-gambar dari HTML soal/opsi ke dokumen Word (jika filenya ada). */
    protected function writeWordImages($section, ?string $html): void
    {
        foreach ($this->localImagePaths($html) as $path) {
            try {
                [$w, $h] = @getimagesize($path) ?: [0, 0];
                // Batasi lebar maks ±400pt, jaga rasio
                $width = $w > 0 ? min(400, $w * 0.75) : 300;
                $section->addImage($path, [
                    'width' => $width,
                    'height' => ($w > 0 && $h > 0) ? $width * $h / $w : null,
                ]);
            } catch (\Throwable) {
                // gambar rusak/tidak terbaca → lewati, jangan gagalkan export
            }
        }
    }

    /** Ambil path file lokal dari semua <img src> yang menunjuk /storage/... */
    protected function localImagePaths(?string $html): array
    {
        if (! $html || stripos($html, '<img') === false) return [];

        preg_match_all('~<img[^>]*?\ssrc=["\']([^"\']+)["\']~i', $html, $m);
        $paths = [];
        foreach ($m[1] as $src) {
            $src = html_entity_decode($src, ENT_QUOTES | ENT_HTML5);
            if (! preg_match('~/storage/([^"\'?#]+)~', $src, $sm)) continue;
            $file = public_path('storage/'.$sm[1]);
            if (is_file($file)) $paths[] = $file;
        }
        return $paths;
    }

    /**
     * HTML soal → HTML aman untuk dompdf (dipanggil dari view export-pdf):
     *  - tag dibatasi allowlist (termasuk img/sup/sub supaya pangkat & gambar TAMPIL)
     *  - <img src> di-embed sebagai data URI base64 — dompdf tidak perlu akses
     *    jaringan/chroot, gambar pasti muncul selama filenya ada di storage
     */
    public static function pdfHtml(?string $html): string
    {
        if ($html === null || $html === '') return '';

        $html = strip_tags($html, '<br><strong><b><em><i><u><sup><sub><p><span><img><table><tr><td><th><ul><ol><li>');

        return preg_replace_callback(
            '~(<img[^>]*?\ssrc=["\'])([^"\']+)(["\'][^>]*>)~i',
            function ($m) {
                $src = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5);
                if (str_starts_with($src, 'data:image/')) return $m[0];

                if (preg_match('~/storage/([^"\'?#]+)~', $src, $sm)) {
                    $file = public_path('storage/'.$sm[1]);
                    if (is_file($file)) {
                        $mime = mime_content_type($file) ?: 'image/png';
                        return $m[1].'data:'.$mime.';base64,'.base64_encode(file_get_contents($file)).$m[3];
                    }
                }
                // File tidak ketemu / URL eksternal → ganti placeholder teks
                return '<span style="color:#999">[gambar tidak tersedia]</span>';
            },
            $html
        );
    }

    /**
     * Hasilkan PNG placeholder (via GD) untuk dipakai sebagai contoh gambar soal
     * di template. Null bila ekstensi GD tidak tersedia — template tetap valid,
     * hanya tanpa gambar contoh.
     */
    protected function placeholderImagePng(string $label): ?string
    {
        if (! function_exists('imagecreatetruecolor')) return null;

        try {
            $w = 340; $h = 136;
            $im = imagecreatetruecolor($w, $h);
            $bg     = imagecolorallocate($im, 239, 246, 255); // biru sangat muda
            $border = imagecolorallocate($im, 31, 71, 245);   // brand
            $ink    = imagecolorallocate($im, 30, 41, 59);     // ink-900

            imagefilledrectangle($im, 0, 0, $w - 1, $h - 1, $bg);
            imagerectangle($im, 0, 0, $w - 1, $h - 1, $border);

            $title = imagefontwidth(5) * strlen($label);
            imagestring($im, 5, (int) max(6, ($w - $title) / 2), 44, $label, $ink);
            $sub = 'Ganti dengan gambar soal Anda';
            $sw = imagefontwidth(3) * strlen($sub);
            imagestring($im, 3, (int) max(6, ($w - $sw) / 2), 78, $sub, $ink);

            ob_start();
            imagepng($im);
            $bytes = ob_get_clean();
            imagedestroy($im);

            return $bytes !== '' ? $bytes : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function typeSlug(Question $q): string
    {
        return $q->type->slug ?? 'pg';
    }

    protected function slug(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/i', '-', strtolower($s));
    }
}
