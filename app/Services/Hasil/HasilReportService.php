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
     * EXPORT STATISTIK PER QUIZ — format "HASIL NILAI TES"
     * (DATA UMUM + tabel nilai per siswa + rekapitulasi + tanda tangan)
     * ============================================================ */
    public function exportStatistik(Quiz $quiz, Collection $attempts, array $meta): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hasil Nilai Tes');

        $totalMarks = (float) ($meta['total_marks'] ?: 100);
        $kktp = (float) ($meta['kktp'] ?? 70);

        // Hitung skor & nilai (0-100) tiap siswa dari skor mentah (quiz_attempts.score).
        $siswaRows = [];
        foreach ($attempts as $a) {
            $siswa = $a->siswa;
            $skor  = (float) ($a->score ?? 0);
            $nilai = $totalMarks > 0 ? round($skor / $totalMarks * 100, 2) : 0;
            $siswaRows[] = [
                'nama'  => mb_strtoupper(optional($siswa)->nama_siswa ?? '-'),
                'skor'  => $skor,
                'nilai' => $nilai,
                'tuntas'=> $nilai >= $kktp,
            ];
        }

        $n = count($siswaRows);
        $nilaiList = array_column($siswaRows, 'nilai');
        $jumlah   = array_sum($nilaiList);
        $rata     = $n ? $jumlah / $n : 0;
        $tertinggi= $n ? max($nilaiList) : 0;
        $terendah = $n ? min($nilaiList) : 0;
        $simpangan= $this->stddev($nilaiList, $rata);
        $tuntas   = count(array_filter($siswaRows, fn ($s) => $s['tuntas']));
        $belum    = $n - $tuntas;
        $diAtas   = count(array_filter($nilaiList, fn ($v) => $v > $rata));
        $diBawah  = count(array_filter($nilaiList, fn ($v) => $v < $rata));

        // ------- JUDUL -------
        $lastCol = 'E';
        $sheet->setCellValue('A1', 'HASIL NILAI TES');
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A1:{$lastCol}1")->getBorders()->getBottom()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getStyle("A1:{$lastCol}1")->getBorders()->getTop()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM);
        $sheet->getRowDimension(1)->setRowHeight(22);

        // ------- DATA UMUM -------
        $infoStart = 3;
        $infoRows = [
            ['NAMA SEKOLAH', $meta['nama_sekolah']],
            ['MATA PELAJARAN', $meta['mapel']],
            ['KELAS/SEMESTER/TAHUN', $meta['kelas_semester_tahun']],
            ['NAMA TES', $meta['nama_tes']],
            ['MATERI POKOK', $meta['materi_pokok']],
            ['TUJUAN PEMBELAJARAN', $meta['tujuan_pembelajaran']],
            ['TANGGAL TES', $meta['tanggal_tes'] instanceof \DateTimeInterface
                ? $meta['tanggal_tes']->translatedFormat('d F Y') : (string) $meta['tanggal_tes']],
            ['KKTP', (string) $kktp],
            ['NAMA PENGAJAR', $meta['nama_pengajar']],
            ['NIP', $meta['nip_pengajar']],
        ];
        $infoEnd = $infoStart + count($infoRows) - 1;

        $sheet->mergeCells("A{$infoStart}:A{$infoEnd}");
        $sheet->setCellValue("A{$infoStart}", 'DATA UMUM');
        $sheet->getStyle("A{$infoStart}")->getAlignment()
            ->setTextRotation(90)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A{$infoStart}")->getFont()->setBold(true);

        $row = $infoStart;
        foreach ($infoRows as [$label, $value]) {
            $sheet->setCellValueExplicit("B{$row}", $label, DataType::TYPE_STRING);
            $sheet->getStyle("B{$row}")->getFont()->setBold(true);
            $sheet->setCellValueExplicit("C{$row}", ':', DataType::TYPE_STRING);
            $sheet->mergeCells("D{$row}:{$lastCol}{$row}");
            $sheet->setCellValueExplicit("D{$row}", (string) $value, DataType::TYPE_STRING);
            $row++;
        }
        $sheet->getStyle("A{$infoStart}:{$lastCol}{$infoEnd}")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // ------- TABEL NILAI SISWA -------
        $row = $infoEnd + 2;
        $headers = ['No', 'Nama Siswa', 'Jumlah Skor', 'Nilai', 'Keterangan Ketuntasan Belajar'];
        $sheet->fromArray([$headers], null, "A{$row}");
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $tableHeaderRow = $row;
        $row++;

        $no = 1;
        foreach ($siswaRows as $s) {
            $sheet->setCellValue("A{$row}", $no++);
            $sheet->setCellValueExplicit("B{$row}", $s['nama'], DataType::TYPE_STRING);
            $sheet->setCellValue("C{$row}", $s['skor']);
            $sheet->setCellValue("D{$row}", $s['nilai']);
            $sheet->setCellValueExplicit("E{$row}", $s['tuntas'] ? 'Tuntas' : 'Belum Tuntas', DataType::TYPE_STRING);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}:D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }
        if ($row > $tableHeaderRow + 1) {
            $sheet->getStyle("A{$tableHeaderRow}:{$lastCol}" . ($row - 1))
                ->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        }

        // ------- REKAPITULASI (2 kolom berdampingan) -------
        $row += 1;
        $recapLeft = [
            ['Jumlah', number_format($jumlah, 0)],
            ['Rata-rata', number_format($rata, 0)],
            ['Nilai Tertinggi', number_format($tertinggi, 0)],
            ['Nilai Terendah', number_format($terendah, 0)],
            ['Simpangan Baku', number_format($simpangan, 0)],
        ];
        $tuntasPct = $n ? round($tuntas / $n * 100) : 0;
        $belumPct  = $n ? round($belum / $n * 100) : 0;
        $recapRight = [
            ['Jumlah Peserta Ujian', $n . ' Orang'],
            ["Jumlah Yang Tuntas {$tuntasPct}%", $tuntas . ' Orang'],
            ["Jumlah Yang Belum {$belumPct}%", $belum . ' Orang'],
            ['Di Atas Rata-rata', $diAtas . ' Orang'],
            ['Di Bawah Rata-rata', $diBawah . ' Orang'],
        ];
        foreach ($recapLeft as $i => [$label, $value]) {
            $r = $row + $i;
            $sheet->setCellValueExplicit("A{$r}", $label, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("B{$r}", ':', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$r}", (string) $value, DataType::TYPE_STRING);

            [$rlabel, $rvalue] = $recapRight[$i];
            $sheet->setCellValueExplicit("D{$r}", $rlabel . ' :', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("E{$r}", (string) $rvalue, DataType::TYPE_STRING);
        }
        $row += count($recapLeft) + 2;

        // ------- TANDA TANGAN -------
        $sheet->setCellValueExplicit("D{$row}", $meta['kota'] . ', ' . now()->translatedFormat('d F Y'), DataType::TYPE_STRING);
        $sheet->mergeCells("D{$row}:{$lastCol}{$row}");
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->setCellValueExplicit("A{$row}", 'Kepala Sekolah', DataType::TYPE_STRING);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValueExplicit("D{$row}", 'Guru Mata Pelajaran', DataType::TYPE_STRING);
        $sheet->mergeCells("D{$row}:{$lastCol}{$row}");
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row += 4;
        $sheet->setCellValueExplicit("A{$row}", $meta['kepala_sekolah'], DataType::TYPE_STRING);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setUnderline(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValueExplicit("D{$row}", $meta['nama_pengajar'], DataType::TYPE_STRING);
        $sheet->mergeCells("D{$row}:{$lastCol}{$row}");
        $sheet->getStyle("D{$row}")->getFont()->setBold(true)->setUnderline(true);
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->setCellValueExplicit("A{$row}", 'NIP ' . $meta['nip_kepala_sekolah'], DataType::TYPE_STRING);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValueExplicit("D{$row}", 'NIP ' . $meta['nip_pengajar'], DataType::TYPE_STRING);
        $sheet->mergeCells("D{$row}:{$lastCol}{$row}");
        $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ------- LEBAR KOLOM -------
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(26);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(22);
        $sheet->getColumnDimension('E')->setWidth(24);

        return $this->stream($spreadsheet, 'hasil-nilai-tes-' . $this->slug($quiz->name) . '-' . date('Ymd') . '.xlsx');
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

    /** Simpangan baku sampel (n-1). */
    protected function stddev(array $arr, float $mean): float
    {
        $n = count($arr);
        if ($n < 2) {
            return 0;
        }
        $sum = 0;
        foreach ($arr as $v) {
            $sum += ($v - $mean) ** 2;
        }
        return sqrt($sum / ($n - 1));
    }
}
