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
            $section->addText('#JUDUL: '.$q->title);
            $section->addText('#SOAL: '.html_entity_decode(strip_tags($q->question)));

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

        $filename = $this->slug($title).'-'.date('Ymd-His').'.pdf';
        return $pdf->download($filename);
    }

    /**
     * Generate template Word (.docx) berisi 5 contoh soal (satu per jenis)
     * dengan format yang KOMPATIBEL dengan importer Word.
     */
    public function templateWord(): StreamedResponse
    {
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
        $section->addText('#JUDUL: Ibu Kota Indonesia');
        $section->addText('#SOAL: Ibu kota negara Indonesia adalah ___');
        $section->addText('#JAWABAN: Jakarta');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 4: Benar / Salah =====
        $section->addText('#JENIS: benar-salah', ['bold' => true]);
        $section->addText('#MAPEL: IPS');
        $section->addText('#TINGKAT: 10');
        $section->addText('#JUDUL: Bumi Bulat');
        $section->addText('#SOAL: Bumi berbentuk bulat sempurna.');
        $section->addText('#JAWABAN: S');
        $section->addText('---');
        $section->addTextBreak(1);

        // ===== Contoh 5: Penjodohan =====
        $section->addText('#JENIS: penjodohan', ['bold' => true]);
        $section->addText('#MAPEL: IPA');
        $section->addText('#TINGKAT: 10');
        $section->addText('#JUDUL: Pasangkan Organ');
        $section->addText('#SOAL: Pasangkan organ dengan fungsinya');
        $section->addText('A. Jantung');
        $section->addText('B. Paru-paru');
        $section->addText('C. Hati');
        $section->addText('#JAWABAN: A=Memompa darah; B=Pernapasan; C=Detoksifikasi');
        $section->addText('---');
        $section->addTextBreak(1);

        // Catatan
        $section->addTextBreak(1);
        $section->addText('Catatan format:', ['bold' => true]);
        $section->addListItem('Setiap soal diawali #JENIS dan ditutup baris "---".');
        $section->addListItem('Kode jenis: pg, pgk, fill-blank, benar-salah, penjodohan.');
        $section->addListItem('Kode #MAPEL harus sama dengan kode mapel di Data Center.');
        $section->addListItem('Tingkat opsional (1–12). Boleh dikosongkan.');
        $section->addListItem('Jawaban PG = huruf tunggal (A/B/C/D/E).');
        $section->addListItem('Jawaban PGK = huruf dipisah koma (mis. A,C,E).');
        $section->addListItem('Jawaban Benar-Salah = B (benar) atau S (salah).');
        $section->addListItem('Jawaban Penjodohan = "huruf=teks" dipisah titik koma.');

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
            'jenis', 'mapel_kode', 'tingkat',
            'judul', 'pertanyaan',
            'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e',
            'jawaban',
        ];
        $sheet->fromArray([$headers], null, 'A1');
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F47F5');
        $sheet->getStyle('A1:K1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:K1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Paksa kolom data jadi TEXT supaya Excel tidak auto-convert
        // (mis. kode mapel "7-1", jawaban "A,C,E", angka tetap text)
        $sheet->getStyle('A2:K9999')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        foreach (range('A', 'K') as $col) {
            $sheet->getStyle($col)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        // Contoh baris untuk tiap jenis
        $examples = [
            ['pg',          'MTK', '10', 'Akar 144',            'Berapa akar dari 144?',                '10','11','12','13','',           'C'],
            ['pgk',         'MTK', '10', 'Bilangan prima',      'Manakah bilangan prima?',              '2','4','7','9','11',             'A,C,E'],
            ['fill-blank',  'BIN', '10', 'Ibu kota Indonesia',  'Ibu kota negara Indonesia adalah ___', '','','','','',                   'Jakarta'],
            ['benar-salah', 'IPS', '10', 'Bumi bulat',          'Bumi berbentuk bulat sempurna.',       '','','','','',                   'S'],
            ['penjodohan',  'IPA', '10', 'Pasangkan organ',     'Pasangkan organ dengan fungsinya',     'Jantung','Paru-paru','Hati','','', 'A=Memompa darah; B=Pernapasan; C=Detoksifikasi'],
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

        foreach (range('A', 'K') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'template-import-soal.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
                    $section->addText("$letter. ".$opt->option_text);
                    if ($opt->is_correct) $correct[] = $letter;
                    $letter++;
                }
                $section->addText('#JAWABAN: '.implode(',', $correct));
                break;

            case 'benar-salah':
                foreach ($q->options as $opt) {
                    if ($opt->is_correct) {
                        $section->addText('#JAWABAN: '.(strtoupper(substr($opt->option_text, 0, 1)) === 'B' ? 'B' : 'S'));
                        break;
                    }
                }
                break;

            case 'fill-blank':
                $section->addText('#JAWABAN: '.($q->correct_answer_text ?? ''));
                break;

            case 'penjodohan':
                $kiri = $q->options->where('is_left_side', true)->sortBy('order')->values();
                $kanan = $q->options->where('is_left_side', false)->keyBy('pair_group');
                $letter = 'A'; $pairs = [];
                foreach ($kiri as $opt) {
                    $section->addText("$letter. ".$opt->option_text);
                    if (isset($kanan[$opt->pair_group])) {
                        $pairs[] = $letter.'='.$kanan[$opt->pair_group]->option_text;
                    }
                    $letter++;
                }
                $section->addText('#JAWABAN: '.implode('; ', $pairs));
                break;
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
