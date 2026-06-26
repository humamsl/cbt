<?php

namespace App\Services\Master;

use App\Models\Guru;
use App\Models\GuruMapel;
use App\Models\MataPelajaran;
use App\Models\RombonganBelajar;
use App\Models\TahunAjaran;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export assignment Guru ↔ Mapel ↔ Rombel (Excel).
 *
 * Kolom (baris 1):
 *   nip | kode_mapel | nama_rombel | tahun_ajaran
 *
 * - nip          : NIP guru (harus sudah terdaftar)
 * - kode_mapel   : kode mapel (harus sudah terdaftar)
 * - nama_rombel  : nama rombel persis (pada TA yg ditulis di kolom tahun_ajaran,
 *                  jika kosong → pakai TA aktif)
 * - tahun_ajaran : nama TA (opsional). Jika kosong → TA aktif.
 *
 * Logika: firstOrCreate berdasarkan kombinasi 4 kolom.
 */
class GuruMapelExcelService
{
    public const HEADERS = [
        'nip', 'kode_mapel', 'nama_rombel', 'tahun_ajaran',
    ];

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));
        $taAktif = TahunAjaran::aktif();

        // Cache lookup biar cepat
        $guruCache  = Guru::pluck('id', 'nip');
        $mapelCache = MataPelajaran::pluck('id', 'kode_mapel');
        $taCache    = TahunAjaran::pluck('id', 'nama_tahun_ajaran');

        foreach ($data as $i => $row) {
            $line = $i + 2;
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }

                $nip        = trim((string) ($assoc['nip'] ?? ''));
                $kodeMapel  = trim((string) ($assoc['kode_mapel'] ?? ''));
                $namaRombel = trim((string) ($assoc['nama_rombel'] ?? ''));
                $namaTa     = trim((string) ($assoc['tahun_ajaran'] ?? ''));

                if ($nip === '' || $kodeMapel === '' || $namaRombel === '') {
                    throw new \RuntimeException('nip, kode_mapel, & nama_rombel wajib diisi');
                }

                $guruId  = $guruCache[$nip]  ?? null;
                $mapelId = $mapelCache[$kodeMapel] ?? null;

                if (! $guruId)  throw new \RuntimeException("Guru NIP '{$nip}' tidak ditemukan");
                if (! $mapelId) throw new \RuntimeException("Mapel kode '{$kodeMapel}' tidak ditemukan");

                $taId = null;
                if ($namaTa !== '') {
                    $taId = $taCache[$namaTa] ?? null;
                    if (! $taId) throw new \RuntimeException("Tahun ajaran '{$namaTa}' tidak ditemukan");
                } else {
                    if (! $taAktif) throw new \RuntimeException('Tidak ada Tahun Ajaran aktif');
                    $taId = $taAktif->id;
                }

                $rombel = RombonganBelajar::where('nama_rombel', $namaRombel)
                    ->where('tahun_ajaran_id', $taId)->first();
                if (! $rombel) {
                    throw new \RuntimeException("Rombel '{$namaRombel}' tidak ada di TA terkait");
                }

                GuruMapel::firstOrCreate([
                    'guru_id'              => $guruId,
                    'mata_pelajaran_id'    => $mapelId,
                    'rombongan_belajar_id' => $rombel->id,
                    'tahun_ajaran_id'      => $taId,
                ]);

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = "Baris {$line}: " . $e->getMessage();
            }
        }

        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $items = null): StreamedResponse
    {
        $items ??= GuruMapel::with('guru', 'mapel', 'rombel', 'tahunAjaran')
                    ->orderBy('guru_id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Guru Mapel');

        // Header gabungan: read-friendly + raw-key. Header impor di baris 1 = key.
        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $items->map(fn ($gm) => [
            optional($gm->guru)->nip,
            optional($gm->mapel)->kode_mapel,
            optional($gm->rombel)->nama_rombel,
            optional($gm->tahunAjaran)->nama_tahun_ajaran,
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'data-guru-mapel-' . date('Ymd-His') . '.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Guru Mapel');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['198001012000031000', 'MTK', '7-1',     '2024/2025 - Ganjil'],
            ['198001012000031000', 'MTK', 'X IPA 2', '2024/2025 - Ganjil'],
            ['198502102001012001', 'BIN', 'XI IPS 1', ''],
        ], 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'template-import-guru-mapel.xlsx');
    }

    /* ---------- helpers ---------- */

    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:' . chr(64 + $colCount) . '1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /**
     * Paksa SEMUA kolom data jadi format TEXT supaya Excel tidak meng-auto-convert
     * "7-1" jadi tanggal, NIP panjang jadi notasi ilmiah, dsb.
     */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = chr(64 + $colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_TEXT);
        // Set juga ke kolom dimension supaya berlaku ke baris baru yang di-tambah user
        for ($i = 1; $i <= $colCount; $i++) {
            $col = chr(64 + $i);
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris-baris data dengan tipe STRING eksplisit (anti auto-cast Excel). */
    protected function writeRowsAsText($sheet, array $rows, int $startRow = 2): void
    {
        foreach ($rows as $rIdx => $row) {
            $r = $startRow + $rIdx;
            $cIdx = 0;
            foreach ($row as $value) {
                $col = chr(65 + $cIdx);
                $sheet->setCellValueExplicit("{$col}{$r}", (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}

if (! class_exists(ImportResult::class, false)) {
    class ImportResult
    {
        public int $success = 0;
        public int $failed = 0;
        public array $errors = [];
    }
}
