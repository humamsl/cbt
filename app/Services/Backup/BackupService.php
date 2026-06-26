<?php

namespace App\Services\Backup;

use App\Models\Guru;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\QuestionType;
use App\Models\Siswa;
use App\Services\Master\GuruExcelService;
use App\Services\Master\SiswaExcelService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Backup / Restore data master (guru, siswa, bank soal) ke / dari satu file ZIP.
 * Format isi:
 *   guru.xlsx       — Excel kompatibel dgn GuruExcelService::HEADERS
 *   siswa.xlsx      — Excel kompatibel dgn SiswaExcelService::HEADERS
 *   bank-soal.json  — Soal + opsi (anti-loss kolom HTML)
 *   meta.json       — info backup (versi & tanggal)
 */
class BackupService
{
    public function __construct(
        protected GuruExcelService $guruSvc,
        protected SiswaExcelService $siswaSvc,
    ) {}

    /** Build backup ZIP & return BinaryFileResponse untuk download. */
    public function downloadZip(array $modules): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cbtbk_');
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Tidak bisa membuat arsip ZIP');
        }

        $meta = [
            'app' => config('app.name'),
            'app_version' => 'cbt-v2',
            'backup_at'  => now()->toIso8601String(),
            'modules'    => $modules,
        ];

        if (in_array('guru', $modules, true)) {
            $zip->addFromString('guru.xlsx', $this->guruXlsxBinary());
            $meta['count_guru'] = Guru::count();
        }
        if (in_array('siswa', $modules, true)) {
            $zip->addFromString('siswa.xlsx', $this->siswaXlsxBinary());
            $meta['count_siswa'] = Siswa::count();
        }
        if (in_array('bank-soal', $modules, true)) {
            $zip->addFromString('bank-soal.json', $this->bankSoalJson());
            $meta['count_questions'] = Question::count();
        }

        $zip->addFromString('meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        $filename = 'backup-cbt-'.now()->format('Ymd-His').'.zip';
        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Restore dari ZIP. Return array ringkasan: ['guru' => [success, failed, errors], ...]
     */
    public function restoreZip(UploadedFile $file): array
    {
        $tmpDir = sys_get_temp_dir().'/cbtres_'.uniqid();
        mkdir($tmpDir, 0777, true);

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            throw new \RuntimeException('File ZIP tidak valid.');
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        $summary = [];

        if (is_file("$tmpDir/guru.xlsx")) {
            $upload = new UploadedFile("$tmpDir/guru.xlsx", 'guru.xlsx', null, null, true);
            $r = $this->guruSvc->import($upload);
            $summary['guru'] = ['success' => $r->success, 'failed' => $r->failed, 'errors' => $r->errors];
        }
        if (is_file("$tmpDir/siswa.xlsx")) {
            $upload = new UploadedFile("$tmpDir/siswa.xlsx", 'siswa.xlsx', null, null, true);
            $r = $this->siswaSvc->import($upload);
            $summary['siswa'] = ['success' => $r->success, 'failed' => $r->failed, 'errors' => $r->errors];
        }
        if (is_file("$tmpDir/bank-soal.json")) {
            $summary['bank-soal'] = $this->restoreBankSoalJson(file_get_contents("$tmpDir/bank-soal.json"));
        }

        // bersihkan
        $this->rrmdir($tmpDir);

        return $summary;
    }

    /* ---------- internal ---------- */

    protected function guruXlsxBinary(): string
    {
        $stream = $this->guruSvc->export();
        ob_start();
        $stream->sendContent();
        return ob_get_clean();
    }

    protected function siswaXlsxBinary(): string
    {
        $stream = $this->siswaSvc->export();
        ob_start();
        $stream->sendContent();
        return ob_get_clean();
    }

    /** Bank soal di-export ke JSON karena ada HTML / longtext. */
    protected function bankSoalJson(): string
    {
        $rows = Question::with('options', 'type', 'mapel', 'topic')->get()->map(function ($q) {
            return [
                'title'             => $q->title,
                'question'          => $q->question,
                'question_type'     => optional($q->type)->question_type,
                'mata_pelajaran'    => optional($q->mapel)->kode_mapel,
                'topic'             => optional($q->topic)->topic,
                'tingkat'           => $q->tingkat,
                'tingkat_kesulitan' => $q->tingkat_kesulitan,
                'pembahasan'        => $q->pembahasan,
                'is_active'         => (bool) $q->is_active,
                'options' => $q->options->map(fn ($o) => [
                    'label'        => $o->label,
                    'option_text'  => $o->option_text,
                    'is_correct'   => (bool) $o->is_correct,
                    'order'        => $o->order,
                    'is_left_side' => $o->is_left_side,
                    'pair_group'   => $o->pair_group,
                ])->values()->all(),
            ];
        });

        return json_encode([
            'version' => 1,
            'questions' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    protected function restoreBankSoalJson(string $raw): array
    {
        $data = json_decode($raw, true) ?: [];
        $rows = $data['questions'] ?? [];
        $success = 0; $failed = 0; $errors = [];

        $typeMap  = QuestionType::pluck('id', 'question_type');
        $mapelMap = \App\Models\MataPelajaran::pluck('id', 'kode_mapel');
        $topicMap = \App\Models\Topic::pluck('id', 'topic');

        foreach ($rows as $i => $row) {
            try {
                DB::transaction(function () use ($row, $typeMap, $mapelMap, $topicMap, &$success) {
                    $q = Question::firstOrCreate(
                        ['title' => (string) ($row['title'] ?? '-'), 'mata_pelajaran_id' => $mapelMap[$row['mata_pelajaran'] ?? null] ?? null],
                        [
                            'question'          => (string) ($row['question'] ?? ''),
                            'question_type_id'  => $typeMap[$row['question_type'] ?? null] ?? null,
                            'topic_id'          => $topicMap[$row['topic'] ?? null] ?? null,
                            'tingkat'           => $row['tingkat'] ?? null,
                            'tingkat_kesulitan' => $row['tingkat_kesulitan'] ?? 'sedang',
                            'pembahasan'        => $row['pembahasan'] ?? null,
                            'is_active'         => (bool) ($row['is_active'] ?? true),
                        ]
                    );
                    // Reset & rebuild opsi
                    $q->options()->delete();
                    foreach (($row['options'] ?? []) as $idx => $opt) {
                        QuestionOption::create([
                            'question_id'  => $q->id,
                            'label'        => $opt['label'] ?? chr(65 + $idx),
                            'option_text'  => $opt['option_text'] ?? '',
                            'is_correct'   => (bool) ($opt['is_correct'] ?? false),
                            'order'        => $opt['order'] ?? $idx + 1,
                            'is_left_side' => $opt['is_left_side'] ?? null,
                            'pair_group'   => $opt['pair_group'] ?? null,
                        ]);
                    }
                    $success++;
                });
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = "Soal ke-".($i + 1).": ".$e->getMessage();
            }
        }

        return compact('success', 'failed', 'errors');
    }

    protected function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) return;
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = "$dir/$entry";
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
