<?php

namespace App\Services\Soal;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;

/**
 * Service untuk mengimport soal dari file Excel (.xlsx) atau Word (.docx).
 *
 * --- Format Excel ---
 * Kolom (header wajib di baris 1):
 *   jenis | mapel_kode | tingkat | judul | pertanyaan |
 *   opsi_a | opsi_b | opsi_c | opsi_d | opsi_e | jawaban
 *
 * - jenis: pg | pgk | fill-blank | penjodohan | benar-salah
 * - jawaban (per jenis):
 *     pg          → huruf A/B/C/D/E
 *     pgk         → daftar huruf dipisah koma, mis. "A,C"
 *     fill-blank  → teks jawaban
 *     benar-salah → "B" atau "S" (atau "Benar"/"Salah")
 *     penjodohan  → format "A=val1; B=val2; C=val3" (kiri=kanan)
 *
 * --- Format Word (.docx) ---
 * Satu blok soal dipisah oleh "---":
 *   #JENIS: pg
 *   #MAPEL: MTK
 *   #JUDUL: Akar pangkat
 *   #SOAL: Berapa hasil akar dari 144?
 *   A. 10
 *   B. 11
 *   C. 12
 *   D. 13
 *   #JAWABAN: C
 *   ---
 */
class ImportSoalService
{
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
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false); // 0-indexed
        if (count($data) < 2) return [];

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));
        $rows = [];
        foreach ($data as $row) {
            $assoc = [];
            foreach ($headers as $i => $h) {
                $assoc[$h] = $row[$i] ?? null;
            }
            if (empty($assoc['pertanyaan'])) continue;

            $rows[] = $this->normalizeRow($assoc);
        }
        return $rows;
    }

    /*   --- WORD PARSER   --- */
    protected function parseWord(UploadedFile $file): array
    {
        $phpWord = WordIOFactory::load($file->getRealPath(), 'Word2007');
        $lines = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $el) {
                $text = $this->elementText($el);
                if ($text !== '') $lines[] = $text;
            }
        }

        // Pisah per blok "---"
        $blocks = [];
        $current = [];
        foreach ($lines as $line) {
            if (preg_match('/^-{3,}$/', trim($line))) {
                if ($current) { $blocks[] = $current; $current = []; }
            } else {
                $current[] = $line;
            }
        }
        if ($current) $blocks[] = $current;

        $rows = [];
        foreach ($blocks as $block) {
            $assoc = ['jenis' => 'pg'];
            $options = [];
            foreach ($block as $line) {
                $t = trim($line);
                if (preg_match('/^#(JENIS|MAPEL|TINGKAT|JUDUL|SOAL|JAWABAN):\s*(.+)$/i', $t, $m)) {
                    $key = strtolower($m[1]);
                    $val = trim($m[2]);
                    $map = [
                        'jenis' => 'jenis', 'mapel' => 'mapel_kode', 'tingkat' => 'tingkat',
                        'judul' => 'judul',
                        'soal' => 'pertanyaan', 'jawaban' => 'jawaban',
                    ];
                    $assoc[$map[$key] ?? $key] = $val;
                } elseif (preg_match('/^([A-E])\.\s*(.+)$/i', $t, $m)) {
                    $options['opsi_'.strtolower($m[1])] = trim($m[2]);
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

        $opsi = [];
        foreach (['a','b','c','d','e'] as $k) {
            $v = $r['opsi_'.$k] ?? null;
            if ($v !== null && trim((string) $v) !== '') {
                $opsi[strtoupper($k)] = trim((string) $v);
            }
        }

        return [
            'jenis'      => $jenis,
            'mapel_kode' => trim((string) ($r['mapel_kode'] ?? '')) ?: null,
            'tingkat'    => $r['tingkat'] ?? null,
            'judul'      => trim((string) ($r['judul'] ?? mb_substr($r['pertanyaan'], 0, 60))),
            'pertanyaan' => trim((string) $r['pertanyaan']),
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

                DB::transaction(function () use ($r, $typeMap, $mapelMap, $guruId) {
                    $q = Question::create([
                        'title'              => $r['judul'],
                        'question'           => $r['pertanyaan'],
                        'question_type_id'   => $typeMap[$r['jenis']],
                        'mata_pelajaran_id'  => $mapelMap[$r['mapel_kode']] ?? null,
                        'tingkat'            => is_numeric($r['tingkat']) ? (int) $r['tingkat'] : null,
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
