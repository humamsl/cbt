<?php

namespace App\Services\Master;

use App\Models\Guru;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import / Export data guru via Excel (.xlsx).
 *
 * Header kolom (urut, baris 1):
 *   nip | nama_ptk | email | nomor_hp | jenis_kelamin | tempat_lahir |
 *   tanggal_lahir | alamat | jabatan | status_kepegawaian | password | is_aktif
 *
 * - jenis_kelamin: L atau P
 * - tanggal_lahir: format YYYY-MM-DD (atau date Excel)
 * - status_kepegawaian: PNS, PPPK, GTT, Honorer
 * - password: opsional. Jika kosong, default = password
 * - is_aktif: 1 / 0 / kosong (default 1)
 */
class GuruExcelService
{
    public const HEADERS = [
        'nip','nama_ptk','email','nomor_hp','jenis_kelamin','tempat_lahir',
        'tanggal_lahir','alamat','jabatan','status_kepegawaian','password','is_aktif',
    ];

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));

        foreach ($data as $i => $row) {
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }
                if (empty($assoc['nip']) || empty($assoc['nama_ptk'])) continue;

                $payload = [
                    'nama_ptk'           => trim((string) $assoc['nama_ptk']),
                    'email'              => $assoc['email'] ?: null,
                    'nomor_hp'           => $assoc['nomor_hp'] ?: null,
                    'jenis_kelamin'      => in_array(strtoupper((string) $assoc['jenis_kelamin']), ['L','P'], true)
                                                ? strtoupper($assoc['jenis_kelamin']) : null,
                    'tempat_lahir'       => $assoc['tempat_lahir'] ?: null,
                    'tanggal_lahir'      => $this->parseDate($assoc['tanggal_lahir'] ?? null),
                    'alamat'             => $assoc['alamat'] ?: null,
                    'jabatan'            => $assoc['jabatan'] ?: null,
                    'status_kepegawaian' => $assoc['status_kepegawaian'] ?: null,
                    'is_aktif'           => $this->parseBool($assoc['is_aktif'] ?? 1, true),
                ];

                $pwd = trim((string) ($assoc['password'] ?? ''));
                if ($pwd !== '') {
                    $payload['password'] = Hash::make($pwd);
                }

                $g = Guru::where('nip', $assoc['nip'])->first();
                if ($g) {
                    $g->update($payload);
                } else {
                    // Akun baru: password default = password jika tidak diisi
                    $payload['password'] = $payload['password'] ?? Hash::make((string) ($assoc['password'] ?? 'password'));
                    $payload['nip'] = (string) $assoc['nip'];
                    Guru::create($payload);
                }
                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = 'Baris '.($i + 2).': '.$e->getMessage();
            }
        }
        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $guru = null): StreamedResponse
    {
        $guru ??= Guru::orderBy('nama_ptk')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Guru');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $guru->map(fn ($g) => [
            $g->nip, $g->nama_ptk, $g->email, $g->nomor_hp, $g->jenis_kelamin,
            $g->tempat_lahir, optional($g->tanggal_lahir)->format('Y-m-d'),
            $g->alamat, $g->jabatan, $g->status_kepegawaian,
            '', // password kosong saat export (alasan keamanan)
            $g->is_aktif ? '1' : '0',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'data-guru-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Guru');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        // 2 baris contoh
        $this->writeRowsAsText($sheet, [
            ['198001012000031000', 'Andi Wijaya, S.Pd.', 'andi@sekolah.test', '081234567890', 'L', 'Bandung', '1980-01-01', 'Jl. Mawar 1', 'Guru',    'PNS',  '', '1'],
            ['198502102001012001', 'Sri Wahyuni, M.Pd.',  'sri@sekolah.test',  '081234567891', 'P', 'Bogor',   '1985-02-10', 'Jl. Melati 2', 'Wakasek', 'PPPK', '', '1'],
        ], 2);

        foreach (range('A', chr(64 + count(self::HEADERS))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'template-import-guru.xlsx');
    }

    /*    helpers    */
    protected function styleHeader($sheet, int $colCount): void
    {
        $range = 'A1:'.chr(64 + $colCount).'1';
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    /** Paksa kolom data jadi format TEXT supaya Excel tidak auto-cast (NIP, tanggal "7-1", dll). */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = chr(64 + $colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $col = chr(64 + $i);
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }
    }

    /** Tulis baris dengan tipe STRING eksplisit, anti auto-cast. */
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

    protected function parseDate($value): ?string
    {
        if (! $value) return null;
        try {
            if (is_numeric($value)) {
                // Excel serial date
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)->format('Y-m-d');
            }
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseBool($v, bool $default = true): bool
    {
        if ($v === null || $v === '') return $default;
        if (is_bool($v)) return $v;
        $v = strtolower((string) $v);
        return in_array($v, ['1','y','yes','true','aktif','active'], true);
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
