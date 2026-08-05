<?php

namespace App\Services\Backup;

use App\Models\MataPelajaran;
use App\Models\Question;
use App\Models\QuestionType;
use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Backup / Restore Bank Soal (satu-satunya data yang masih dimiliki CBT
 * sendiri — guru & siswa sekarang dikelola di aplikasi Data Center) ke / dari
 * satu file ZIP.
 * Format isi:
 *   bank-soal.json  — Soal + opsi (anti-loss kolom HTML)
 *   meta.json       — info backup (versi & tanggal)
 *
 * CATATAN VERSI FORMAT
 * v1 (lama) menulis `topic` sebagai string nama saja dan tidak menyertakan
 * correct_answer_text/media. v2 menulis `topic` sebagai objek (nama + mapel +
 * tingkat) supaya topik bisa dibuat ulang di instalasi yang masih kosong.
 * Restore tetap menerima v1 — nama topik string diperlakukan sebagai topik
 * milik mapel & tingkat soal itu sendiri.
 */
class BackupService
{
    /** Build backup ZIP & return BinaryFileResponse untuk download. */
    public function downloadZip(array $modules): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cbtbk_');
        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Tidak bisa membuat arsip ZIP');
        }

        $meta = [
            'app' => config('app.name'),
            'app_version' => 'cbt-v2',
            'backup_at' => now()->toIso8601String(),
            'modules' => $modules,
        ];

        $jsonPath = null;
        if (in_array('bank-soal', $modules, true)) {
            // Ditulis bertahap ke file, bukan dirakit jadi satu string di
            // memori: bank soal sekolah bisa puluhan MB dan HTML soal berat.
            $jsonPath = $tmp.'.json';
            $this->writeBankSoalJson($jsonPath);
            $zip->addFile($jsonPath, 'bank-soal.json');
            $meta['count_questions'] = Question::count();
        }

        $zip->addFromString('meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        if ($jsonPath !== null) {
            @unlink($jsonPath);
        }

        $filename = 'backup-cbt-'.now()->format('Ymd-His').'.zip';

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Restore dari ZIP. Return array ringkasan: ['bank-soal' => [...]]
     */
    public function restoreZip(UploadedFile $file): array
    {
        $tmpDir = sys_get_temp_dir().'/cbtres_'.uniqid();
        mkdir($tmpDir, 0777, true);

        $zip = new ZipArchive;
        if ($zip->open($file->getRealPath()) !== true) {
            throw new \RuntimeException('File ZIP tidak valid.');
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        $summary = [];

        if (is_file("$tmpDir/bank-soal.json")) {
            $summary['bank-soal'] = $this->restoreBankSoalJson(file_get_contents("$tmpDir/bank-soal.json"));
        }

        // bersihkan
        $this->rrmdir($tmpDir);

        return $summary;
    }

    /* ---------- internal ---------- */

    /** Bank soal ditulis ke JSON karena isinya HTML / longtext. */
    protected function writeBankSoalJson(string $path): void
    {
        $fh = fopen($path, 'w');
        fwrite($fh, '{"version":2,"questions":[');
        $pertama = true;

        Question::with('options', 'type', 'mapel', 'topic', 'topic.mapel')
            ->chunkById(200, function ($rows) use ($fh, &$pertama) {
                foreach ($rows as $q) {
                    fwrite($fh, $pertama ? '' : ',');
                    $pertama = false;
                    fwrite($fh, json_encode($this->soalKeArray($q), JSON_UNESCAPED_UNICODE));
                }
            });

        fwrite($fh, ']}');
        fclose($fh);
    }

    /** @return array<string,mixed> */
    protected function soalKeArray(Question $q): array
    {
        return [
            'title' => $q->title,
            'question' => $q->question,
            'question_type' => optional($q->type)->question_type,
            'question_type_slug' => optional($q->type)->slug,
            'mata_pelajaran' => optional($q->mapel)->kode_mapel,
            'topic' => $q->topic ? [
                'topic' => $q->topic->topic,
                'tingkat' => $q->topic->tingkat,
                'mata_pelajaran' => optional($q->topic->mapel)->kode_mapel,
            ] : null,
            'tingkat' => $q->tingkat,
            'tingkat_kesulitan' => $q->tingkat_kesulitan,
            'pembahasan' => $q->pembahasan,
            // Kunci Fill the Blank ada di kolom ini, bukan di baris opsi —
            // tanpa ini soal isian kehilangan jawabannya setiap kali direstore.
            'correct_answer_text' => $q->correct_answer_text,
            'case_sensitive' => (bool) $q->case_sensitive,
            'media_url' => $q->media_url,
            'media_type' => $q->media_type,
            'is_active' => (bool) $q->is_active,
            'options' => $q->options->map(fn ($o) => [
                'option_text' => $o->option_text,
                'is_correct' => (bool) $o->is_correct,
                'order' => $o->order,
                'is_left_side' => (bool) $o->is_left_side,
                'pair_group' => $o->pair_group,
                'media_url' => $o->media_url,
                'media_type' => $o->media_type,
            ])->values()->all(),
        ];
    }

    /**
     * Masukkan soal dari bank-soal.json.
     *
     * Soal dikenali dari ISI-nya (judul + pertanyaan + mapel + tingkat), bukan
     * dari judulnya saja. Judul kembar itu lumrah — satu paket ujian sering
     * memberi judul sama ke puluhan soal ("Chapter 4", "ASTS Ganjil") — dan
     * menyamakan soal berdasarkan judul membuat soal-soal berbeda saling
     * menimpa sampai sebagian besar banknya lenyap.
     *
     * Soal yang isinya sudah persis sama DILEWATI, bukan ditimpa: restore
     * dipakai untuk memulihkan yang hilang, jadi tidak boleh membuang
     * perbaikan yang sudah dikerjakan guru lewat aplikasi.
     */
    protected function restoreBankSoalJson(string $raw): array
    {
        $data = json_decode($raw, true) ?: [];
        $rows = $data['questions'] ?? [];
        $dibuat = 0;
        $dilewati = 0;
        $gagal = 0;
        $errors = [];

        $typeMap = QuestionType::pluck('id', 'question_type')->all();
        $typeSlugMap = QuestionType::pluck('id', 'slug')->all();
        $mapelMap = MataPelajaran::pluck('id', 'kode_mapel')->all();
        $adaHash = $this->hashSoalTerpasang();
        $topikMap = $this->petaTopik();

        // Satu transaksi untuk seluruh berkas: kalau gagal di tengah jalan,
        // bank soal tidak ditinggalkan separuh terisi.
        DB::transaction(function () use (
            $rows, $typeMap, $typeSlugMap, $mapelMap, &$adaHash, &$topikMap,
            &$dibuat, &$dilewati, &$gagal, &$errors
        ) {
            $now = now();
            $bufferOpsi = [];

            foreach ($rows as $i => $row) {
                try {
                    $mapelId = $mapelMap[$row['mata_pelajaran'] ?? null] ?? null;
                    $tingkat = $row['tingkat'] ?? null;
                    $judul = (string) ($row['title'] ?? '-');
                    $isi = (string) ($row['question'] ?? '');

                    $hash = $this->hashIsi($judul, $isi, $mapelId, $tingkat);
                    if (isset($adaHash[$hash])) {
                        $dilewati++;

                        continue;
                    }

                    $typeId = $typeSlugMap[$row['question_type_slug'] ?? null]
                        ?? $typeMap[$row['question_type'] ?? null]
                        ?? null;

                    $qId = DB::table('questions')->insertGetId([
                        'title' => Str::limit($judul, 188),
                        'question' => $isi,
                        'question_type_id' => $typeId,
                        'mata_pelajaran_id' => $mapelId,
                        'topic_id' => $this->topikId($row, $mapelId, $tingkat, $mapelMap, $topikMap, $now),
                        'tingkat' => $tingkat,
                        'tingkat_kesulitan' => $row['tingkat_kesulitan'] ?? 'sedang',
                        'pembahasan' => $row['pembahasan'] ?? null,
                        'correct_answer_text' => $row['correct_answer_text'] ?? null,
                        'case_sensitive' => (bool) ($row['case_sensitive'] ?? false),
                        'media_url' => $row['media_url'] ?? null,
                        'media_type' => $row['media_type'] ?? null,
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $adaHash[$hash] = $qId;
                    $dibuat++;

                    foreach (($row['options'] ?? []) as $idx => $opt) {
                        $bufferOpsi[] = [
                            'question_id' => $qId,
                            'option_text' => $opt['option_text'] ?? '',
                            'is_correct' => (bool) ($opt['is_correct'] ?? false),
                            'order' => min((int) ($opt['order'] ?? $idx), 255),
                            // NOT NULL di tabel — berkas lama / buatan tangan
                            // sering tidak menyertakannya.
                            'is_left_side' => (bool) ($opt['is_left_side'] ?? true),
                            'pair_group' => $opt['pair_group'] ?? null,
                            'media_url' => $opt['media_url'] ?? null,
                            'media_type' => $opt['media_type'] ?? null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if (count($bufferOpsi) >= 1000) {
                        DB::table('question_options')->insert($bufferOpsi);
                        $bufferOpsi = [];
                    }
                } catch (\Throwable $e) {
                    $gagal++;
                    if (count($errors) < 20) {
                        $errors[] = 'Soal ke-'.($i + 1).': '.$e->getMessage();
                    }
                }
            }

            if ($bufferOpsi !== []) {
                DB::table('question_options')->insert($bufferOpsi);
            }
        });

        return [
            'success' => $dibuat,      // nama lama, masih dipakai view
            'dibuat' => $dibuat,
            'dilewati' => $dilewati,
            'failed' => $gagal,
            'errors' => $errors,
        ];
    }

    /**
     * Cari / buat topik. Restore versi lama hanya mencocokkan nama topik yang
     * kebetulan sudah ada, sehingga di instalasi baru (tabel topics masih
     * kosong) SEMUA soal masuk tanpa topik.
     *
     * @param  array<string,mixed>  $row
     * @param  array<string,int>  $mapelMap
     * @param  array<string,int>  $topikMap
     */
    protected function topikId(array $row, ?int $mapelId, $tingkat, array $mapelMap, array &$topikMap, $now): ?int
    {
        $t = $row['topic'] ?? null;
        if ($t === null || $t === '') {
            return null;
        }

        // v1: string nama saja. v2: objek {topic, tingkat, mata_pelajaran}.
        if (is_string($t)) {
            $nama = $t;
            $tTingkat = $tingkat;
            $tMapel = $mapelId;
        } else {
            $nama = (string) ($t['topic'] ?? '');
            $tTingkat = $t['tingkat'] ?? $tingkat;
            $tMapel = $mapelMap[$t['mata_pelajaran'] ?? null] ?? $mapelId;
        }
        if ($nama === '') {
            return null;
        }

        $kunci = mb_strtolower($nama).'|'.($tMapel ?? '').'|'.($tTingkat ?? '');
        if (isset($topikMap[$kunci])) {
            return $topikMap[$kunci];
        }

        return $topikMap[$kunci] = DB::table('topics')->insertGetId([
            'topic' => Str::limit($nama, 188),
            'slug' => Str::limit(Str::slug($nama) ?: 'topik', 188),
            'mata_pelajaran_id' => $tMapel,
            'tingkat' => $tTingkat,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Sidik jari isi soal yang sudah ada, dibaca bertahap supaya bank soal
     * besar tidak menghabiskan memori.
     *
     * @return array<string,int> hash => question id
     */
    protected function hashSoalTerpasang(): array
    {
        $peta = [];
        Question::withTrashed()
            ->select('id', 'title', 'question', 'mata_pelajaran_id', 'tingkat')
            ->chunkById(500, function ($rows) use (&$peta) {
                foreach ($rows as $q) {
                    $peta[$this->hashIsi($q->title, $q->question, $q->mata_pelajaran_id, $q->tingkat)] = $q->id;
                }
            });

        return $peta;
    }

    /** @return array<string,int> "nama|mapel|tingkat" => topic id */
    protected function petaTopik(): array
    {
        $peta = [];
        foreach (Topic::select('id', 'topic', 'mata_pelajaran_id', 'tingkat')->get() as $t) {
            $peta[mb_strtolower($t->topic).'|'.($t->mata_pelajaran_id ?? '').'|'.($t->tingkat ?? '')] = $t->id;
        }

        return $peta;
    }

    /** Beda spasi / baris baru jangan dianggap soal yang berbeda. */
    protected function hashIsi(?string $judul, ?string $isi, ?int $mapelId, $tingkat): string
    {
        $rapikan = fn ($s) => preg_replace('/\s+/u', ' ', trim((string) $s));

        return sha1(implode('|', [
            $rapikan($judul),
            $rapikan($isi),
            $mapelId ?? '',
            $tingkat ?? '',
        ]));
    }

    protected function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "$dir/$entry";
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
