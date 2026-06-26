<?php

namespace App\Services\Hasil;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service report Hasil & Laporan:
 *  - exportNilai       : daftar nilai siswa (filter mapel + rombel/tingkat)
 *  - exportStatistik   : ringkasan statistik per quiz
 *  - exportAnalisisButir : item-analysis per soal (P-value, D-index, distribusi)
 */
class HasilReportService
{
    /* ============================================================
     * EXPORT NILAI SISWA
     * ============================================================ */
    public function exportNilai(Collection $attempts, array $meta = []): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Nilai Siswa');

        // ------- HEADER INFO -------
        $sheet->setCellValue('A1', 'LAPORAN NILAI SISWA');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $infoLines = [
            'Mata Pelajaran : '.($meta['mapel'] ?? '-'),
            'Target         : '.($meta['target'] ?? '-'),
            'Tahun Ajaran   : '.($meta['tahun_ajaran'] ?? '-'),
            'Dicetak        : '.now()->translatedFormat('d F Y H:i'),
        ];
        $row = 3;
        foreach ($infoLines as $line) {
            $sheet->setCellValue("A{$row}", $line);
            $sheet->mergeCells("A{$row}:I{$row}");
            $row++;
        }

        // ------- TABEL HEADER -------
        $row += 1;
        $headers = ['No', 'NISN', 'Nama Siswa', 'Rombel', 'Tingkat', 'Ujian', 'Nilai', 'Status', 'Selesai'];
        $sheet->fromArray([$headers], null, "A{$row}");
        $headerRow = $row;
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle("A{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ------- DATA -------
        $row++;
        $no = 1;
        foreach ($attempts as $h) {
            $siswa  = $h->siswa;
            $rombel = optional($siswa)->rombelSekarang->rombel ?? null;
            $tingkat = $rombel->tingkat ?? '-';
            $namaRombel = $rombel->nama_rombel ?? '-';

            $statusLabel = match (true) {
                $h->is_blocked   => 'Diblokir',
                $h->is_done      => 'Selesai',
                $h->time_start   => 'Sedang',
                default          => 'Belum',
            };

            $this->writeStringCells($sheet, $row, [
                'A' => (string) $no++,
                'B' => optional($siswa)->nisn ?? '-',
                'C' => optional($siswa)->nama_siswa ?? '-',
                'D' => $namaRombel,
                'E' => (string) $tingkat,
                'F' => optional($h->quiz)->name ?? '-',
            ]);
            $sheet->setCellValue("G{$row}", $h->score !== null ? (float) $h->score : null);
            $sheet->setCellValueExplicit("H{$row}", $statusLabel, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit(
                "I{$row}",
                $h->time_end ? $h->time_end->format('Y-m-d H:i') : '-',
                DataType::TYPE_STRING
            );
            $row++;
        }

        // Border
        if ($row > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:I" . ($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }

        // Auto size
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->stream($spreadsheet, 'nilai-siswa-' . date('Ymd-His') . '.xlsx');
    }

    /* ============================================================
     * EXPORT STATISTIK PER QUIZ
     * ============================================================ */
    public function exportStatistik(Quiz $quiz, array $stats): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Statistik');

        $sheet->setCellValue('A1', 'STATISTIK NILAI UJIAN');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Ujian');
        $sheet->setCellValueExplicit('B3', $quiz->name, DataType::TYPE_STRING);
        $sheet->setCellValue('A4', 'Mapel');
        $sheet->setCellValueExplicit('B4', optional($quiz->mapel)->nama_mapel ?? '-', DataType::TYPE_STRING);
        $sheet->setCellValue('A5', 'Total Soal');
        $sheet->setCellValue('B5', $stats['total_soal'] ?? 0);

        $row = 7;
        $sheet->setCellValue("A{$row}", 'Metrik');
        $sheet->setCellValue("B{$row}", 'Nilai');
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:B{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $row++;

        $rows = [
            ['Total peserta',          $stats['total_peserta'] ?? 0],
            ['Peserta selesai',        $stats['peserta_selesai'] ?? 0],
            ['Rata-rata',              number_format($stats['mean'] ?? 0, 2)],
            ['Median',                 number_format($stats['median'] ?? 0, 2)],
            ['Nilai tertinggi',        number_format($stats['max'] ?? 0, 2)],
            ['Nilai terendah',         number_format($stats['min'] ?? 0, 2)],
            ['Standar deviasi',        number_format($stats['stddev'] ?? 0, 2)],
            ['% Lulus (≥ KKM)',        number_format($stats['pass_rate'] ?? 0, 1) . '%'],
            ['KKM',                    $stats['kkm'] ?? 70],
        ];
        foreach ($rows as $r) {
            $sheet->setCellValueExplicit("A{$row}", (string) $r[0], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", (string) $r[1], DataType::TYPE_STRING);
            $row++;
        }

        // Distribusi nilai
        $row += 1;
        $sheet->setCellValue("A{$row}", 'Distribusi Nilai');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        foreach (($stats['distribution'] ?? []) as $range => $count) {
            $sheet->setCellValueExplicit("A{$row}", (string) $range, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", (int) $count);
            $row++;
        }

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(20);

        return $this->stream($spreadsheet, 'statistik-' . $this->slug($quiz->name) . '-' . date('Ymd') . '.xlsx');
    }

    /* ============================================================
     * EXPORT ANALISIS BUTIR SOAL
     * ============================================================ */
    public function exportAnalisisButir(Quiz $quiz, array $items): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Analisis Butir');

        $sheet->setCellValue('A1', 'ANALISIS BUTIR SOAL');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'Ujian: ' . $quiz->name);
        $sheet->setCellValue('A4', 'Mapel: ' . (optional($quiz->mapel)->nama_mapel ?? '-'));
        $sheet->setCellValue('A5', 'Dicetak: ' . now()->translatedFormat('d F Y H:i'));

        $row = 7;
        $headers = ['No', 'Judul Soal', '% Benar', 'P (Kesukaran)', 'Kategori', 'D (Daya Pembeda)', 'Kategori', 'Rekomendasi'];
        $sheet->fromArray([$headers], null, "A{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle("A{$row}:H{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $headerRow = $row;
        $row++;

        $no = 1;
        foreach ($items as $it) {
            $this->writeStringCells($sheet, $row, [
                'A' => (string) $no++,
                'B' => (string) ($it['title'] ?? '-'),
                'C' => number_format(($it['percent_correct'] ?? 0), 1) . '%',
                'D' => number_format($it['p'] ?? 0, 3),
                'E' => (string) ($it['p_kategori'] ?? '-'),
                'F' => number_format($it['d'] ?? 0, 3),
                'G' => (string) ($it['d_kategori'] ?? '-'),
                'H' => (string) ($it['rekomendasi'] ?? '-'),
            ]);
            $row++;
        }

        if ($row > $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:H" . ($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ------- LEGEND -------
        $row += 2;
        $sheet->setCellValue("A{$row}", 'Keterangan');
        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;
        $legend = [
            ['P (Tingkat Kesukaran)', '< 0.30 = Sukar  |  0.30–0.70 = Sedang  |  > 0.70 = Mudah'],
            ['D (Daya Pembeda)',      '< 0.20 = Jelek  |  0.20–0.30 = Cukup  |  0.30–0.70 = Baik  |  > 0.70 = Sangat Baik'],
            ['Rekomendasi',           'Diterima | Perlu Revisi | Dibuang/Ganti'],
        ];
        foreach ($legend as $r) {
            $sheet->setCellValueExplicit("A{$row}", $r[0], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$row}", $r[1], DataType::TYPE_STRING);
            $sheet->mergeCells("B{$row}:H{$row}");
            $row++;
        }

        return $this->stream($spreadsheet, 'analisis-butir-' . $this->slug($quiz->name) . '-' . date('Ymd') . '.xlsx');
    }

    /* ============================================================
     * HELPERS
     * ============================================================ */
    protected function writeStringCells($sheet, int $row, array $cells): void
    {
        foreach ($cells as $col => $val) {
            $sheet->setCellValueExplicit("{$col}{$row}", (string) ($val ?? ''), DataType::TYPE_STRING);
        }
    }

    protected function stream(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function slug(string $s): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $s)) ?: 'report';
    }
}
