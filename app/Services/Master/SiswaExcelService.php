<?php

namespace App\Services\Master;

use App\Models\RombonganBelajar;
use App\Models\Siswa;
use App\Models\SiswaRombel;
use App\Models\TahunAjaran;
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
 * Import / Export data siswa via Excel (.xlsx).
 *
 * Header kolom (urut, baris 1):
 *   nisn | nis | nama_siswa | jenis_kelamin | tempat_lahir | tanggal_lahir |
 *   agama | alamat | nomor_hp | email | nama_ayah | nama_ibu | nomor_hp_ortu |
 *   rombel | password | is_aktif
 *
 * - jenis_kelamin: L atau P
 * - tanggal_lahir: YYYY-MM-DD
 * - rombel: nama rombel pada Tahun Ajaran aktif (mis. "X IPA 1"). Akan di-cari & dipasang otomatis.
 * - password: opsional. Default = 12345678.
 * - is_aktif: 1 / 0 / kosong (default 1)
 */
class SiswaExcelService
{
    public const HEADERS = [
        'nisn','nis','nama_siswa','jenis_kelamin','tempat_lahir','tanggal_lahir',
        'agama','alamat','nomor_hp','email','nama_ayah','nama_ibu','nomor_hp_ortu',
        'rombel','password','is_aktif',
    ];

    public function import(UploadedFile $file): ImportResult
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $data  = $sheet->toArray(null, true, true, false);
        $result = new ImportResult();

        if (count($data) < 2) return $result;

        $headers = array_map(fn ($v) => trim(strtolower((string) $v)), array_shift($data));

        $ta = TahunAjaran::aktif();
        $rombelMap = $ta
            ? RombonganBelajar::where('tahun_ajaran_id', $ta->id)->pluck('id', 'nama_rombel')->toArray()
            : [];

        foreach ($data as $i => $row) {
            try {
                $assoc = [];
                foreach ($headers as $idx => $h) {
                    $assoc[$h] = $row[$idx] ?? null;
                }
                if (empty($assoc['nisn']) || empty($assoc['nama_siswa'])) continue;

                $payload = [
                    'nis'           => $assoc['nis'] ?: null,
                    'nama_siswa'    => trim((string) $assoc['nama_siswa']),
                    'jenis_kelamin' => in_array(strtoupper((string) $assoc['jenis_kelamin']), ['L','P'], true)
                                          ? strtoupper($assoc['jenis_kelamin']) : null,
                    'tempat_lahir'  => $assoc['tempat_lahir'] ?: null,
                    'tanggal_lahir' => $this->parseDate($assoc['tanggal_lahir'] ?? null),
                    'agama'         => $assoc['agama'] ?: null,
                    'alamat'        => $assoc['alamat'] ?: null,
                    'nomor_hp'      => $assoc['nomor_hp'] ?: null,
                    'email'         => $assoc['email'] ?: null,
                    'nama_ayah'     => $assoc['nama_ayah'] ?: null,
                    'nama_ibu'      => $assoc['nama_ibu'] ?: null,
                    'nomor_hp_ortu' => $assoc['nomor_hp_ortu'] ?: null,
                    'is_aktif'      => $this->parseBool($assoc['is_aktif'] ?? 1, true),
                ];

                $pwd = trim((string) ($assoc['password'] ?? ''));
                if ($pwd !== '') {
                    $payload['password'] = Hash::make($pwd);
                }

                DB::transaction(function () use ($assoc, $payload, $rombelMap, $ta) {
                    $s = Siswa::where('nisn', $assoc['nisn'])->first();
                    if ($s) {
                        $s->update($payload);
                    } else {
                        $payload['password'] = $payload['password'] ?? Hash::make((string) $assoc['nisn']);
                        $payload['nisn'] = (string) $assoc['nisn'];
                        $s = Siswa::create($payload);
                    }

                    // Sync rombel jika kolom diisi dan TA aktif tersedia
                    $rombelName = trim((string) ($assoc['rombel'] ?? ''));
                    if ($rombelName !== '' && $ta) {
                        $rombelId = $rombelMap[$rombelName] ?? null;
                        if ($rombelId) {
                            SiswaRombel::updateOrCreate(
                                ['siswa_id' => $s->id, 'tahun_ajaran_id' => $ta->id],
                                ['rombongan_belajar_id' => $rombelId]
                            );
                        } else {
                            throw new \RuntimeException("Rombel '{$rombelName}' tidak ditemukan di TA aktif.");
                        }
                    }
                });

                $result->success++;
            } catch (\Throwable $e) {
                $result->failed++;
                $result->errors[] = 'Baris '.($i + 2).': '.$e->getMessage();
            }
        }
        return $result;
    }

    public function export(?\Illuminate\Database\Eloquent\Collection $siswa = null): StreamedResponse
    {
        $siswa ??= Siswa::with('rombelSekarang.rombel')->orderBy('nama_siswa')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $rows = $siswa->map(fn ($s) => [
            $s->nisn, $s->nis, $s->nama_siswa, $s->jenis_kelamin,
            $s->tempat_lahir, optional($s->tanggal_lahir)->format('Y-m-d'),
            $s->agama, $s->alamat, $s->nomor_hp, $s->email,
            $s->nama_ayah, $s->nama_ibu, $s->nomor_hp_ortu,
            optional($s->rombelSekarang?->rombel ?? null)->nama_rombel,
            '', // password kosong
            $s->is_aktif ? '1' : '0',
        ])->toArray();

        $this->writeRowsAsText($sheet, $rows, 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'data-siswa-'.date('Ymd-His').'.xlsx');
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Siswa');

        $sheet->fromArray([self::HEADERS], null, 'A1');
        $this->styleHeader($sheet, count(self::HEADERS));
        $this->forceTextColumns($sheet, count(self::HEADERS));

        $this->writeRowsAsText($sheet, [
            ['009900000001','NIS0001','Ahmad Fauzi','L','Jakarta','2009-05-12','Islam','Jl. Anggrek 1','081200000001','ahmad@test','Budi','Siti','081200000099','7-1','','1'],
            ['009900000002','NIS0002','Bunga Citra','P','Bekasi','2009-07-22','Islam','Jl. Mawar 2','081200000002','bunga@test','Hasan','Aminah','081200000098','X IPA 2','','1'],
        ], 2);

        $this->autoSize($sheet, count(self::HEADERS));
        return $this->stream($spreadsheet, 'template-import-siswa.xlsx');
    }

    /*    helpers    */
    protected function styleHeader($sheet, int $colCount): void
    {
        $lastCol = $this->colLetter($colCount);
        $range = "A1:{$lastCol}1";
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }

    protected function autoSize($sheet, int $colCount): void
    {
        for ($i = 1; $i <= $colCount; $i++) {
            $sheet->getColumnDimension($this->colLetter($i))->setAutoSize(true);
        }
    }

    /** Paksa kolom data jadi format TEXT supaya Excel tidak auto-cast (NIS, "7-1", nomor HP). */
    protected function forceTextColumns($sheet, int $colCount, int $maxRow = 9999): void
    {
        $lastCol = $this->colLetter($colCount);
        $sheet->getStyle("A2:{$lastCol}{$maxRow}")
            ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        for ($i = 1; $i <= $colCount; $i++) {
            $col = $this->colLetter($i);
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
                $col = $this->colLetter($cIdx + 1);
                $sheet->setCellValueExplicit("{$col}{$r}", (string) ($value ?? ''), DataType::TYPE_STRING);
                $cIdx++;
            }
        }
    }

    protected function colLetter(int $n): string
    {
        $letter = '';
        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $n = intdiv($n - $mod, 26);
        }
        return $letter;
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
