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
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\VerticalJc;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Service report Hasil & Laporan:
 *  - exportNilai       : daftar nilai siswa (filter mapel + rombel/tingkat)
 *  - exportStatistik   : ringkasan statistik per quiz
 *  - exportAnalisisButir : item-analysis per soal (P-value, D-index, distribusi)
 *  - exportRemidialPengayaan : dokumen Word program remidial & pengayaan
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
            'Ujian          : '.($meta['judul_ujian'] ?? 'Semua Ujian'),
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
            // nilai = skala 0–100 (accessor QuizAttempt::getNilaiAttribute), bukan poin mentah
            $sheet->setCellValue("G{$row}", $h->nilai);
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

        // Nama file ikut judul ujian yang difilter (sama seperti export soal)
        // supaya tiap file tidak lagi bernama generik "nilai-siswa" yang
        // rawan tertukar antar ujian saat diunduh berkali-kali.
        $judul = $meta['judul_ujian'] ?? 'Semua Ujian';
        return $this->stream($spreadsheet, $this->slug($judul) . '-' . date('Ymd-His') . '.xlsx');
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
     * EXPORT PROGRAM REMIDIAL & PENGAYAAN (Word)
     * — mengikuti format dokumen "Program Remidial dan Program Pengayaan"
     *   (data umum + tabel siswa + tanda tangan + pedoman pelaksanaan)
     * ============================================================ */
    public function exportRemidialPengayaan(Quiz $quiz, array $remidial, array $pengayaan, array $meta): StreamedResponse
    {
        // WAJIB: default PhpWord TIDAK meng-escape teks ke XML.
        \PhpOffice\PhpWord\Settings::setOutputEscapingEnabled(true);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Times New Roman');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginLeft' => 1134, 'marginRight' => 1134,
            'marginTop'  => 1134, 'marginBottom' => 1134,
        ]);

        $tanggalUlangan = $meta['tanggal_ulangan'] instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::parse($meta['tanggal_ulangan'])->translatedFormat('d F Y')
            : (string) $meta['tanggal_ulangan'];
        $rencanaRem = $meta['rencana_ulangan_rem'] instanceof \DateTimeInterface
            ? \Illuminate\Support\Carbon::parse($meta['rencana_ulangan_rem'])->translatedFormat('d F Y')
            : '-';

        // ================= JUDUL =================
        $section->addText('PROGRAM REMIDIAL DAN PROGRAM PENGAYAAN',
            ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);

        // ================= A. PROGRAM REMIDIAL =================
        $section->addText('A. PROGRAM REMIDIAL', ['bold' => true, 'size' => 12], ['spaceAfter' => 120]);

        $this->wordInfoTable($section, [
            ['Sekolah',                  $meta['nama_sekolah']],
            ['Kelas / Semester',         $meta['kelas_semester']],
            ['Mata Pelajaran',           $meta['mapel']],
            ['Ulangan Harian ke',        $meta['ulangan_ke']],
            ['Tanggal Ulangan Harian',   $tanggalUlangan],
            ['Bentuk Soal UH',           $meta['bentuk_soal']],
            ['Materi (KD / Indikator)',  $meta['materi_kd']],
            ['Rencana Ulangan Remidial', $rencanaRem],
            ['KKM',                      (string) $meta['kkm']],
        ]);
        $section->addTextBreak();

        // ---- Tabel siswa remidial ----
        $tableStyle = ['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 60, 'width' => 100 * 50, 'unit' => 'pct'];
        $headStyle  = ['bold' => true, 'size' => 9];
        $cellHead   = ['valign' => VerticalJc::CENTER, 'bgColor' => 'F2F2F2'];
        $pCenter    = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
        $pLeft      = ['spaceAfter' => 0];
        $fontCell   = ['size' => 9];

        // lebar kolom (twip) — total ± 9.630
        $w = [450, 1900, 800, 1500, 2100, 1500, 800, 780];

        $table = $section->addTable($tableStyle);
        $table->addRow();
        $headers = [
            'No.', 'Nama Siswa', 'Nilai Ulangan', 'Indikator yang tidak dikuasai',
            'Bentuk Pelaksanaan Pembelajaran Remidial',
            'Nomor Soal yang dikerjakan dalam Tes Remidial', 'Nilai Tes Rem', 'Ket.',
        ];
        foreach ($headers as $i => $h) {
            $table->addCell($w[$i], $cellHead)->addText($h, $headStyle, $pCenter);
        }
        $table->addRow();
        foreach ($w as $i => $width) {
            $table->addCell($width, $cellHead)->addText('( ' . ($i + 1) . ' )', $fontCell, $pCenter);
        }

        if (empty($remidial)) {
            $table->addRow();
            $table->addCell(array_sum($w), ['gridSpan' => count($w)])
                ->addText('Tidak ada siswa di bawah KKM — seluruh siswa tuntas.', $fontCell, $pCenter);
        }

        $no = 1;
        foreach ($remidial as $s) {
            $table->addRow();
            $table->addCell($w[0])->addText($no++ . '.', $fontCell, $pCenter);
            $table->addCell($w[1])->addText($s['nama'], $fontCell, $pLeft);
            $table->addCell($w[2])->addText($this->formatNilai($s['nilai']), $fontCell, $pCenter);
            $table->addCell($w[3])->addText($s['soal_salah'] !== '' ? 'Soal no. ' . $s['soal_salah'] : '-', $fontCell, $pLeft);
            $table->addCell($w[4])->addText($meta['bentuk_remidial'], $fontCell, $pLeft);
            $table->addCell($w[5])->addText($s['soal_salah'] ?: '-', $fontCell, $pCenter);
            $table->addCell($w[6])->addText('', $fontCell, $pCenter);   // diisi manual setelah tes remidial
            $table->addCell($w[7])->addText('', $fontCell, $pCenter);
        }

        $this->wordSignature($section, $meta);

        // ---- Pedoman pelaksanaan remidial (teks tetap, sesuai dokumen) ----
        $section->addTextBreak();
        $section->addText('Bentuk Pelaksanaan Pembelajaran Remedial', ['bold' => true], ['spaceAfter' => 120]);
        $section->addText('1. Cara yang dapat ditempuh', ['bold' => true], $pLeft);
        $this->wordList($section, [
            'Pemberian bimbingan secara khusus dan perorangan bagi peserta didik yang belum atau mengalami kesulitan dalam penguasaan KD/indikator tertentu.',
            'Pemberian tugas-tugas atau perlakuan (treatment) secara khusus, yang sifatnya penyederhanaan dari pelaksanaan pembelajaran regular, antara lain melalui penyederhanaan strategi pembelajaran, penyederhanaan cara penyajian (gambar, model, skema, grafik, rangkuman sederhana), dan penyederhanaan soal/pertanyaan yang diberikan.',
        ]);
        $section->addText('2. Materi dan waktu pelaksanaan program remedial', ['bold' => true], $pLeft);
        $this->wordList($section, [
            'Program remedial diberikan hanya pada KD atau indikator yang belum tuntas.',
            'Program remedial dilaksanakan setelah mengikuti tes/ulangan KD tertentu atau sejumlah KD dalam satu kesatuan.',
        ]);
        $section->addText('Teknik pelaksanaan penugasan/pembelajaran remedial:', ['bold' => true], ['spaceBefore' => 120, 'spaceAfter' => 60]);
        $this->wordList($section, [
            'Penugasan individu diakhiri dengan tes (lisan/tertulis) bila jumlah peserta didik yang mengikuti remedial maksimal 20%.',
            'Penugasan kelompok diakhiri dengan tes individual (lisan/tertulis) bila jumlah peserta didik yang mengikuti remedi lebih dari 20% tetapi kurang dari 50%.',
            'Pembelajaran ulang diakhiri dengan tes individual (tertulis) bila jumlah peserta didik yang mengikuti remedi lebih dari 50%.',
        ]);

        // ================= B. PROGRAM PENGAYAAN =================
        $section->addPageBreak();
        $section->addText('B. PROGRAM PENGAYAAN', ['bold' => true, 'size' => 12], ['spaceAfter' => 120]);

        $this->wordInfoTable($section, [
            ['Sekolah',                 $meta['nama_sekolah']],
            ['Kelas / Semester',        $meta['kelas_semester']],
            ['Mata Pelajaran',          $meta['mapel']],
            ['KKM Mata Pelajaran',      (string) $meta['kkm']],
            ['Materi (KD / Indikator)', $meta['materi_kd']],
        ]);
        $section->addTextBreak();

        $w2 = [450, 2600, 1200, 5380];
        $table = $section->addTable($tableStyle);
        $table->addRow();
        foreach (['No.', 'Nama Siswa', 'Nilai Ulangan', 'Bentuk Pengayaan'] as $i => $h) {
            $table->addCell($w2[$i], $cellHead)->addText($h, $headStyle, $pCenter);
        }

        if (empty($pengayaan)) {
            $table->addRow();
            $table->addCell(array_sum($w2), ['gridSpan' => count($w2)])
                ->addText('Belum ada siswa yang mencapai KKM.', $fontCell, $pCenter);
        }

        $no = 1;
        foreach ($pengayaan as $idx => $s) {
            $table->addRow();
            $table->addCell($w2[0])->addText($no++ . '.', $fontCell, $pCenter);
            $table->addCell($w2[1])->addText($s['nama'], $fontCell, $pLeft);
            $table->addCell($w2[2])->addText($this->formatNilai($s['nilai']), $fontCell, $pCenter);
            // kolom "Bentuk Pengayaan" digabung vertikal — teksnya sekali di baris pertama
            if ($idx === 0) {
                $table->addCell($w2[3], ['vMerge' => 'restart'])
                    ->addText($meta['bentuk_pengayaan'], $fontCell, $pLeft);
            } else {
                $table->addCell($w2[3], ['vMerge' => 'continue']);
            }
        }

        $this->wordSignature($section, $meta);

        // ---- Pedoman pelaksanaan pengayaan (teks tetap, sesuai dokumen) ----
        $section->addTextBreak();
        $section->addText('Pelaksanaan Program Pengayaan', ['bold' => true], ['spaceAfter' => 120]);
        $section->addText('1. Cara yang dapat ditempuh', ['bold' => true], $pLeft);
        $this->wordList($section, [
            'Pemberian bacaan tambahan atau berdiskusi yang bertujuan memperluas wawasan bagi KD tertentu.',
            'Pemberian tugas untuk melakukan analisis gambar, model, grafik.',
            'Memberikan soal-soal latihan tambahan yang bersifat pengayaan.',
            'Membantu guru dalam membimbing teman-temannya yang belum mencapai ketuntasan.',
        ]);
        $section->addText('2. Materi dan waktu pelaksanaan program pengayaan', ['bold' => true], $pLeft);
        $this->wordList($section, [
            'Materi program pengayaan diberikan sesuai dengan KD atau indikator yang dipelajari, bisa berupa penguatan materi yang dipelajari maupun berupa pengembangan materi.',
            'Waktu pelaksanaan program pengayaan adalah setelah mengikuti tes/ulangan KD tertentu atau kesatuan KD tertentu, dan/atau pada saat pembelajaran di mana siswa yang lebih cepat tuntas dibanding dengan teman lainnya dilayani dengan program pengayaan.',
        ]);
        $section->addText(
            'Sebagai bagian integral dari kegiatan pembelajaran, kegiatan pengayaan tidak lepas kaitannya dengan penilaian. '
            . 'Penilaian hasil belajar kegiatan pengayaan tidak sama dengan kegiatan pembelajaran biasa, tetapi cukup dalam bentuk portofolio, '
            . 'dan harus dihargai sebagai nilai tambah (lebih) dari peserta didik yang normal.',
            [], ['spaceBefore' => 120, 'alignment' => Jc::BOTH]
        );

        return $this->streamWord($phpWord,
            'program-remidial-pengayaan-' . $this->slug($quiz->name) . '-' . date('Ymd') . '.docx');
    }

    /** Tabel info "Label : Value" tanpa border untuk header dokumen Word. */
    protected function wordInfoTable($section, array $rows): void
    {
        $table = $section->addTable(['borderSize' => 0, 'borderColor' => 'FFFFFF', 'cellMargin' => 20]);
        foreach ($rows as [$label, $value]) {
            $table->addRow();
            $table->addCell(3000)->addText($label, [], ['spaceAfter' => 40]);
            $table->addCell(300)->addText(':', [], ['spaceAfter' => 40]);
            $table->addCell(6000)->addText((string) $value, [], ['spaceAfter' => 40]);
        }
    }

    /** Blok tanda tangan guru mata pelajaran (rata kanan). */
    protected function wordSignature($section, array $meta): void
    {
        $pRight = ['alignment' => Jc::CENTER, 'indentation' => ['left' => 5400], 'spaceAfter' => 0];
        $section->addTextBreak();
        $section->addText($meta['kota'] . ', ' . now()->translatedFormat('d F Y'), [], $pRight);
        $section->addText('Guru Mata Pelajaran', [], $pRight);
        $section->addTextBreak(3);
        $section->addText($meta['nama_pengajar'], ['bold' => true, 'underline' => 'single'], $pRight);
        $section->addText('NIP. ' . $meta['nip_pengajar'], [], $pRight);
    }

    /** Daftar butir a., b., c. sederhana. */
    protected function wordList($section, array $items): void
    {
        foreach ($items as $i => $text) {
            $section->addText(
                chr(97 + $i) . '. ' . $text,
                [],
                ['indentation' => ['left' => 360, 'hanging' => 240], 'spaceAfter' => 40, 'alignment' => Jc::BOTH]
            );
        }
    }

    /** Format nilai tanpa desimal berlebih (80 bukan 80.0, tapi 72.5 tetap). */
    protected function formatNilai($nilai): string
    {
        $f = (float) $nilai;
        return $f == (int) $f ? (string) (int) $f : number_format($f, 1);
    }

    protected function streamWord(PhpWord $phpWord, string $filename): StreamedResponse
    {
        $writer = WordIOFactory::createWriter($phpWord, 'Word2007');
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ]);
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
