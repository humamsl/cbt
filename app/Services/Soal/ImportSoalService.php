<?php

namespace App\Services\Soal;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpWord\Element\Image as WordImage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

/**
 * Service untuk mengimport soal dari file Excel (.xlsx) atau Word (.docx).
 *
 * --- Format Excel ---
 * Kolom (header wajib di baris 1):
 *   jenis | mapel_kode | tingkat | judul | pertanyaan |
 *   opsi_a | opsi_b | opsi_c | opsi_d | opsi_e | jawaban | gambar
 *
 * - jenis: pg | pgk | fill-blank | penjodohan | benar-salah
 * - jawaban (per jenis):
 *     pg          → huruf A/B/C/D/E
 *     pgk         → daftar huruf dipisah koma, mis. "A,C"
 *     fill-blank  → teks jawaban
 *     benar-salah → "B" atau "S" (atau "Benar"/"Salah")
 *     penjodohan  → format "A=val1; B=val2; C=val3" (kiri=kanan)
 * - gambar (opsional): URL / data-URI gambar soal. Boleh juga menempel
 *   gambar langsung ke dalam sheet (floating image) — akan ditautkan ke
 *   soal pada baris tempat gambar itu berada.
 *
 * --- Format Word (.docx) ---
 * Satu blok soal dipisah oleh "---":
 *   #JENIS: pg
 *   #MAPEL: MTK
 *   #JUDUL: Akar pangkat
 *   #SOAL: Berapa hasil akar dari 144?
 *   [gambar boleh ditempel/insert di sini — ikut ke soal]
 *   A. 10
 *   B. 11
 *   C. 12
 *   D. 13
 *   #JAWABAN: C
 *   ---
 *
 * --- Gambar ---
 * Gambar yang di-embed di .docx/.xlsx, URL eksternal, maupun base64 data-URI
 * semuanya di-unduh & disimpan ke storage/soal lalu ditulis sebagai <img>
 * dalam HTML soal/opsi — sama seperti gambar yang diunggah lewat editor.
 */
class ImportSoalService
{
    /** Cache resolveTopicId() per (nama|mapel|tingkat) selama satu proses import. */
    protected array $topicCache = [];

    public function __construct(protected ImageLocalizer $localizer)
    {
    }

    public function import(UploadedFile $file, ?int $guruId = null): ImportResult
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $rows = match ($ext) {
            'xlsx', 'xls', 'csv' => $this->parseExcel($file),
            'docx', 'doc'         => $this->parseWord($file),
            default => throw new \InvalidArgumentException('Format file tidak didukung. Gunakan .xlsx atau .docx'),
        };

        return $this->persist($rows, $guruId);
    }

    /*   --- EXCEL PARSER   --- */
    protected function parseExcel(UploadedFile $file): array
    {
        $spreadsheet = SpreadsheetIOFactory::load($file->getRealPath());
        // Pilih sheet DATA berdasarkan header 'pertanyaan' — jangan andalkan
        // sheet aktif (file bisa tersimpan dgn tab "Petunjuk" yang sedang dibuka).
        $sheet = $this->pickDataSheet($spreadsheet);
        $data  = $sheet->toArray(null, true, true, false); // 0-indexed
        if (count($data) < 2) return [];

        // Gambar yang di-embed (floating) dalam sheet, dipetakan per baris data.
        // Kunci = indeks 0-based baris data (baris-1 = header) → HTML <img>.
        $imagesByRow = $this->extractExcelImages($sheet);

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));
        $rows = [];
        foreach ($data as $i => $row) {
            $assoc = [];
            foreach ($headers as $j => $h) {
                $assoc[$h] = $row[$j] ?? null;
            }

            // Sisipkan gambar embedded pada baris ini ke akhir pertanyaan.
            if (isset($imagesByRow[$i])) {
                $assoc['pertanyaan'] = trim((string) ($assoc['pertanyaan'] ?? '')).$imagesByRow[$i];
            }

            if (empty($assoc['pertanyaan'])) continue;

            $rows[] = $this->normalizeRow($assoc);
        }
        return $rows;
    }

    /**
     * Cari worksheet yang berisi data soal (punya header 'pertanyaan' di baris 1).
     * Fallback ke sheet aktif bila tidak ada yang cocok.
     */
    protected function pickDataSheet($spreadsheet)
    {
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $first = $sheet->rangeToArray('A1:Z1', null, false, false, false)[0] ?? [];
            foreach ($first as $cell) {
                if (strtolower(trim((string) $cell)) === 'pertanyaan') {
                    return $sheet;
                }
            }
        }
        return $spreadsheet->getActiveSheet();
    }

    /**
     * Ekstrak gambar floating dari sheet → simpan ke storage → petakan ke baris.
     * @return array<int,string> indeks baris data (0-based) → gabungan HTML <img>
     */
    protected function extractExcelImages($sheet): array
    {
        $map = [];
        foreach ($sheet->getDrawingCollection() as $drawing) {
            try {
                // Koordinat jangkar mis. "F2" → nomor baris. Baris 1 = header,
                // jadi indeks data 0-based = nomorBaris - 2.
                if (! preg_match('/(\d+)\s*$/', (string) $drawing->getCoordinates(), $m)) {
                    continue;
                }
                $idx = ((int) $m[1]) - 2;
                if ($idx < 0) continue;

                $bytes = $this->drawingBytes($drawing);
                if ($bytes === null) continue;

                if ($src = $this->localizer->store($bytes)) {
                    $map[$idx] = ($map[$idx] ?? '').'<img src="'.e($src).'">';
                }
            } catch (\Throwable) {
                // gambar bermasalah → lewati, jangan gagalkan import
            }
        }
        return $map;
    }

    /** Ambil byte mentah dari sebuah drawing PhpSpreadsheet (embedded / memory). */
    protected function drawingBytes($drawing): ?string
    {
        // MemoryDrawing (GD) → render ke buffer sesuai fungsi renderingnya.
        if ($drawing instanceof MemoryDrawing) {
            $res = $drawing->getImageResource();
            if (! $res) return null;
            ob_start();
            call_user_func($drawing->getRenderingFunction(), $res);
            $bytes = ob_get_clean();
            return $bytes !== '' ? $bytes : null;
        }

        // Drawing biasa → path "zip://file.xlsx#xl/media/imageN.ext" atau file lokal.
        if (method_exists($drawing, 'getPath')) {
            $path = (string) $drawing->getPath();
            if ($path !== '' && (str_starts_with($path, 'zip://') || is_file($path))) {
                $bytes = @file_get_contents($path);
                return ($bytes !== false && $bytes !== '') ? $bytes : null;
            }
        }
        return null;
    }

    /*   --- WORD PARSER   --- */
    protected function parseWord(UploadedFile $file): array
    {
        $phpWord = WordIOFactory::load($file->getRealPath(), 'Word2007');

        // Token berurutan: baris teks ['text'=>..] & gambar embedded ['img'=>..].
        // Gambar diproses per paragraf SETELAH teksnya, meniru urutan baca dokumen.
        $tokens = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $text = $this->elementText($el);
                if (trim($text) !== '') $tokens[] = ['text' => $text];
                foreach ($this->collectImages($el) as $imgHtml) {
                    $tokens[] = ['img' => $imgHtml];
                }
            }
        }

        // Pisah per blok "---"
        $blocks = [];
        $current = [];
        foreach ($tokens as $tok) {
            if (isset($tok['text']) && preg_match('/^-{3,}$/', trim($tok['text']))) {
                if ($current) { $blocks[] = $current; $current = []; }
            } else {
                $current[] = $tok;
            }
        }
        if ($current) $blocks[] = $current;

        $map = [
            'jenis' => 'jenis', 'mapel' => 'mapel_kode', 'tingkat' => 'tingkat', 'topik' => 'topik',
            'judul' => 'judul', 'soal' => 'pertanyaan', 'jawaban' => 'jawaban',
        ];

        $rows = [];
        foreach ($blocks as $block) {
            $assoc = ['jenis' => 'pg'];
            $options = [];
            // Ke mana gambar berikutnya dilekatkan: 'pertanyaan' atau 'opsi_x'.
            // Default 'pertanyaan' supaya gambar sebelum opsi ikut ke soal.
            $imgTarget = 'pertanyaan';

            foreach ($block as $tok) {
                if (isset($tok['img'])) {
                    if (str_starts_with($imgTarget, 'opsi_')) {
                        $options[$imgTarget] = ($options[$imgTarget] ?? '').$tok['img'];
                    } else {
                        $assoc['pertanyaan'] = ($assoc['pertanyaan'] ?? '').$tok['img'];
                    }
                    continue;
                }

                $t = trim($tok['text']);
                if (preg_match('/^#(JENIS|MAPEL|TINGKAT|TOPIK|JUDUL|SOAL|JAWABAN):\s*(.*)$/i', $t, $m)) {
                    $field = $map[strtolower($m[1])] ?? strtolower($m[1]);
                    $val = trim($m[2]);
                    // Teks soal ditaruh di DEPAN (gambar menyusul setelahnya).
                    $assoc[$field] = ($field === 'pertanyaan')
                        ? $val.($assoc['pertanyaan'] ?? '')
                        : $val;
                    if ($field === 'pertanyaan') $imgTarget = 'pertanyaan';
                } elseif (preg_match('/^([A-E])\.\s*(.*)$/i', $t, $m)) {
                    $optKey = 'opsi_'.strtolower($m[1]);
                    $options[$optKey] = trim($m[2]).($options[$optKey] ?? '');
                    $imgTarget = $optKey;
                }
            }

            $assoc = array_merge($assoc, $options);
            if (! empty($assoc['pertanyaan'])) {
                $rows[] = $this->normalizeRow($assoc);
            }
        }

        return $rows;
    }

    protected function elementText($element): string
    {
        if ($element instanceof WordImage) {
            return ''; // gambar ditangani terpisah oleh collectImages()
        }
        if (method_exists($element, 'getText')) {
            return (string) $element->getText();
        }
        if (method_exists($element, 'getElements')) {
            $out = '';
            foreach ($element->getElements() as $child) {
                $out .= $this->elementText($child);
            }
            return $out;
        }
        return '';
    }

    /**
     * Ekstrak gambar embedded (rekursif) dari sebuah elemen PhpWord → simpan ke
     * storage → kembalikan daftar HTML <img>.
     * @return string[]
     */
    protected function collectImages($element): array
    {
        if ($element instanceof WordImage) {
            $html = $this->wordImageToHtml($element);
            return $html !== null ? [$html] : [];
        }
        $out = [];
        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                foreach ($this->collectImages($child) as $img) {
                    $out[] = $img;
                }
            }
        }
        return $out;
    }

    protected function wordImageToHtml(WordImage $image): ?string
    {
        try {
            $bytes = $image->getImageString();
            if (! is_string($bytes) || $bytes === '') return null;
            $src = $this->localizer->store($bytes);
            return $src ? '<img src="'.e($src).'">' : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /*   --- NORMALIZER   --- */
    protected function normalizeRow(array $r): array
    {
        $jenis = strtolower(trim($r['jenis'] ?? 'pg'));
        $jenisAliases = [
            'pilihan ganda' => 'pg', 'multiple choice' => 'pg',
            'pgk' => 'pgk', 'pilihan ganda kompleks' => 'pgk', 'multi' => 'pgk',
            'fill blank' => 'fill-blank', 'fill the blank' => 'fill-blank', 'isian' => 'fill-blank',
            'menjodohkan' => 'penjodohan', 'matching' => 'penjodohan',
            'benar salah' => 'benar-salah', 'true false' => 'benar-salah', 'b/s' => 'benar-salah',
        ];
        $jenis = $jenisAliases[$jenis] ?? $jenis;

        // Pertanyaan + gambar opsional dari kolom "gambar" (URL / data-URI).
        $pertanyaan = trim((string) ($r['pertanyaan'] ?? ''));
        $gambar = trim((string) ($r['gambar'] ?? ''));
        if ($gambar !== '') {
            $pertanyaan .= '<img src="'.e($gambar).'">';
        }
        // Unduh & lokalkan semua gambar (URL eksternal / base64 → /storage/soal).
        // Gambar embedded sudah ber-src "/storage/..." → dibiarkan oleh localizer.
        $pertanyaan = (string) $this->localizer->localizeHtml($pertanyaan);

        $opsi = [];
        foreach (['a','b','c','d','e'] as $k) {
            $v = $r['opsi_'.$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                $opsi[strtoupper($k)] = (string) $this->localizer->localizeHtml(trim((string) $v));
            }
        }

        // Judul default: teks polos dari pertanyaan (buang tag <img> dll).
        // Soal yang isinya hanya gambar → beri judul generik agar tidak kosong.
        $judul = trim((string) ($r['judul'] ?? ''));
        if ($judul === '') {
            $judul = mb_substr(trim(strip_tags($pertanyaan)), 0, 60) ?: 'Soal (gambar)';
        }

        return [
            'jenis'      => $jenis,
            'mapel_kode' => trim((string) ($r['mapel_kode'] ?? '')) ?: null,
            'tingkat'    => $r['tingkat'] ?? null,
            'topik'      => trim((string) ($r['topik'] ?? '')),
            'judul'      => $judul,
            'pertanyaan' => $pertanyaan,
            'opsi'       => $opsi,
            'jawaban'    => trim((string) ($r['jawaban'] ?? '')),
        ];
    }

    /*   --- PERSIST   --- */
    protected function persist(array $rows, ?int $guruId): ImportResult
    {
        $result = new ImportResult();
        $typeMap = QuestionType::pluck('id', 'slug')->toArray();
        $mapelMap = MataPelajaran::pluck('id', 'kode_mapel')->toArray();

        foreach ($rows as $idx => $r) {
            try {
                if (! isset($typeMap[$r['jenis']])) {
                    throw new \RuntimeException("Jenis '{$r['jenis']}' tidak dikenal");
                }

                if (trim((string) ($r['topik'] ?? '')) === '') {
                    throw new \RuntimeException("Kolom topik wajib diisi");
                }

                $mapelId = $mapelMap[$r['mapel_kode']] ?? null;
                $tingkat = is_numeric($r['tingkat']) ? (int) $r['tingkat'] : null;
                $topicId = $this->resolveTopicId($r['topik'], $mapelId, $tingkat);

                DB::transaction(function () use ($r, $typeMap, $mapelId, $tingkat, $topicId, $guruId) {
                    $q = Question::create([
                        'title'              => $r['judul'],
                        'question'           => $r['pertanyaan'],
                        'question_type_id'   => $typeMap[$r['jenis']],
                        'mata_pelajaran_id'  => $mapelId,
                        'tingkat'            => $tingkat,
                        'topic_id'           => $topicId,
                        'created_by_guru_id' => $guruId,
                        'is_active'          => true,
                    ]);

                    $this->createOptions($q, $r);
                });

                $result->success++;
            } catch (\Throwable $e) {
                $result->errors[] = "Baris ".($idx + 2).": ".$e->getMessage();
                $result->failed++;
            }
        }

        return $result;
    }

    /**
     * Cocokkan nama topik ke master Topic (case-insensitive, dicocokkan pada
     * mapel+tingkat yang sama). Topik yang belum ada otomatis dibuat — sama
     * seperti status kepegawaian pada import guru — supaya import tidak
     * berhenti hanya gara-gara topiknya belum sempat ditambahkan lebih dulu
     * lewat menu Topik.
     */
    protected function resolveTopicId(string $nama, ?int $mapelId, ?int $tingkat): int
    {
        $nama = trim($nama);
        $cacheKey = mb_strtolower($nama).'|'.($mapelId ?? '').'|'.($tingkat ?? '');

        if (isset($this->topicCache[$cacheKey])) {
            return $this->topicCache[$cacheKey];
        }

        $topic = Topic::where('mata_pelajaran_id', $mapelId)
            ->where('tingkat', $tingkat)
            ->whereRaw('LOWER(topic) = ?', [mb_strtolower($nama)])
            ->first();

        if (! $topic) {
            $topic = Topic::create([
                'topic' => $nama,
                'slug' => \Illuminate\Support\Str::slug($nama).'-'.\Illuminate\Support\Str::random(5),
                'mata_pelajaran_id' => $mapelId,
                'tingkat' => $tingkat,
                'is_active' => true,
            ]);
        }

        return $this->topicCache[$cacheKey] = $topic->id;
    }

    protected function createOptions(Question $q, array $r): void
    {
        $jenis = $r['jenis'];
        $opsi = $r['opsi'];
        $jawaban = strtoupper(trim($r['jawaban'] ?? ''));

        switch ($jenis) {
            case 'pg':
                $correct = strtoupper(trim($jawaban));
                foreach ($opsi as $key => $text) {
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $text,
                        'is_correct' => $key === $correct,
                        'order' => ord($key) - ord('A'),
                    ]);
                }
                break;

            case 'pgk':
                $correctSet = array_map('trim', explode(',', $jawaban));
                foreach ($opsi as $key => $text) {
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $text,
                        'is_correct' => in_array($key, $correctSet, true),
                        'order' => ord($key) - ord('A'),
                    ]);
                }
                break;

            case 'benar-salah':
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => 'Benar',
                    'is_correct' => in_array(strtoupper($jawaban), ['B','BENAR','TRUE','T'], true),
                    'order' => 0,
                ]);
                QuestionOption::create([
                    'question_id' => $q->id, 'option_text' => 'Salah',
                    'is_correct' => in_array(strtoupper($jawaban), ['S','SALAH','FALSE','F'], true),
                    'order' => 1,
                ]);
                break;

            case 'fill-blank':
                $q->update(['correct_answer_text' => $r['jawaban']]);
                break;

            case 'penjodohan':
                // Jawaban format: "A=1; B=2; C=3"
                $pairs = [];
                foreach (explode(';', $jawaban) as $part) {
                    if (preg_match('/^\s*([A-Z])\s*=\s*(.+?)\s*$/i', $part, $m)) {
                        $pairs[strtoupper($m[1])] = trim($m[2]);
                    }
                }
                $i = 1;
                foreach ($opsi as $key => $left) {
                    QuestionOption::create([
                        'question_id' => $q->id, 'option_text' => $left,
                        'is_left_side' => true, 'pair_group' => $i,
                        'is_correct' => true, 'order' => ord($key) - ord('A'),
                    ]);
                    if (isset($pairs[$key])) {
                        QuestionOption::create([
                            'question_id' => $q->id, 'option_text' => $pairs[$key],
                            'is_left_side' => false, 'pair_group' => $i,
                            'is_correct' => true, 'order' => ord($key) - ord('A'),
                        ]);
                    }
                    $i++;
                }
                break;
        }
    }
}

class ImportResult
{
    public int $success = 0;
    public int $failed = 0;
    public array $errors = [];
}
