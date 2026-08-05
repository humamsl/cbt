<?php

namespace App\Console\Commands;

use App\Models\MataPelajaran;
use App\Models\QuestionType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import bank soal dari aplikasi CBT versi LAMA (skema `cbt` warisan, dump
 * MariaDB/mysqldump) ke skema aplikasi ini.
 *
 * Kenapa perlu command sendiri: fitur Import Bank Soal di UI hanya menerima
 * .xlsx/.docx dengan template kolom tertentu, dan Restore Backup hanya paham
 * bank-soal.json hasil export aplikasi ini sendiri. Dump SQL sekolah lama
 * berbeda strukturnya — mapel & kelas disimpan di TOPIK, dan topik dihubungkan
 * ke soal lewat tabel polimorfik `topicables`, bukan kolom langsung.
 *
 * Yang dipetakan:
 *   questions.name            -> questions.question
 *   topicables (polimorfik)   -> questions.topic_id (kolom langsung)
 *   topics.name/class/
 *     id_mata_pelajaran       -> topics.topic/tingkat/mata_pelajaran_id
 *   question_options.name     -> question_options.option_text
 *   matching_answers          -> question_options.pair_group
 *   opsi is_correct fill-blank-> questions.correct_answer_text (pemisah '|')
 *   media_url placeholder     -> NULL  ('url' / 'media url' = sampah di sumber)
 *
 * Aman diulang (idempoten): setiap baris yang sudah masuk dicatat di tabel
 * legacy_soal_imports, jadi menjalankan ulang tidak menggandakan soal —
 * hanya melanjutkan sisanya.
 *
 * ALUR PEMAKAIAN
 *
 *  1) Muat dump lama ke database staging (dump utuh boleh, tabel yang tidak
 *     dipakai diabaikan):
 *
 *       mysql -uroot -e "CREATE DATABASE cbt_lama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
 *       mysql -uroot cbt_lama < dump-sekolah.sql
 *
 *  2) Lihat mapel apa saja yang ada di dump (dump TIDAK memuat tabel
 *     mata_pelajaran, hanya id-nya, jadi harus dicocokkan manual):
 *
 *       php artisan soal:import-legacy --source-db=cbt_lama --print-mapel
 *
 *  3) Susun file pemetaan JSON  {"id mapel di dump": "id mapel Data Center"}:
 *
 *       { "2": 5, "3": 6, "7": 3, "16": 4 }
 *
 *  4) Cek dulu tanpa menulis apa pun, lalu jalankan:
 *
 *       php artisan soal:import-legacy --source-db=cbt_lama --mapel-map=map.json --dry-run
 *       php artisan soal:import-legacy --source-db=cbt_lama --mapel-map=map.json --max-tingkat=9
 *
 * Database staging harus berada di SERVER MySQL yang sama dengan database
 * aplikasi, karena transformasinya memakai query lintas-database.
 */
class ImportSoalLegacy extends Command
{
    protected $signature = 'soal:import-legacy
        {--source-db= : Nama database staging berisi dump CBT lama}
        {--mapel-map= : File JSON pemetaan id mapel sumber -> id mata_pelajaran Data Center}
        {--print-mapel : Tampilkan mapel di dump beserta contoh topiknya, lalu keluar}
        {--max-tingkat= : Tingkat tertinggi yang wajar (mis. 9 untuk SMP). Topik ber-tingkat di atasnya digabung ke topik senama yang valid}
        {--guru-id= : Isi created_by_guru_id pada soal hasil import}
        {--batch= : Nama batch (default: nama database sumber)}
        {--rollback : Hapus kembali seluruh hasil import batch ini}
        {--dry-run : Hanya laporkan rencananya, tidak menulis apa pun}';

    protected $description = 'Import bank soal dari dump database aplikasi CBT versi lama';

    /** Tabel yang wajib ada di database staging. */
    protected const TABEL_WAJIB = ['topics', 'topicables', 'questions', 'question_options', 'question_types'];

    /** Nama jenis soal di aplikasi lama -> slug question_types di aplikasi ini. */
    protected const ALIAS_JENIS = [
        'pilihan ganda' => 'pg',
        'multiple choice' => 'pg',
        'pilihan ganda multiple answer' => 'pgk',
        'pilihan ganda kompleks' => 'pgk',
        'multi' => 'pgk',
        'fill the blank' => 'fill-blank',
        'fill blank' => 'fill-blank',
        'isian' => 'fill-blank',
        'penjodohan' => 'penjodohan',
        'menjodohkan' => 'penjodohan',
        'matching' => 'penjodohan',
        'benar salah' => 'benar-salah',
        'benar / salah' => 'benar-salah',
        'true false' => 'benar-salah',
    ];

    protected string $src;

    protected string $batch;

    public function handle(): int
    {
        $this->src = (string) $this->option('source-db');
        if ($this->src === '') {
            $this->error('--source-db wajib diisi. Muat dulu dump lama ke sebuah database staging.');
            $this->line('  mysql -uroot -e "CREATE DATABASE cbt_lama CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"');
            $this->line('  mysql -uroot cbt_lama < dump-sekolah.sql');

            return self::FAILURE;
        }
        if (! preg_match('/^[A-Za-z0-9_]+$/', $this->src)) {
            $this->error('Nama database sumber tidak valid.');

            return self::FAILURE;
        }

        $this->batch = (string) ($this->option('batch') ?: $this->src);

        if (! $this->cekSumber()) {
            return self::FAILURE;
        }

        if ($this->option('rollback')) {
            return $this->rollback();
        }

        if ($this->option('print-mapel')) {
            $this->printMapel();

            return self::SUCCESS;
        }

        $mapelMap = $this->bacaMapelMap();
        if ($mapelMap === null) {
            return self::FAILURE;
        }

        $jenisMap = $this->petakanJenis();
        if ($jenisMap === null) {
            return self::FAILURE;
        }

        return $this->option('dry-run')
            ? $this->laporkanRencana($mapelMap, $jenisMap)
            : $this->jalankanImport($mapelMap, $jenisMap);
    }

    /* ---------------------------------------------------------------- cek */

    protected function cekSumber(): bool
    {
        $ada = DB::table('information_schema.tables')
            ->where('table_schema', $this->src)
            ->pluck('TABLE_NAME')
            ->map(fn ($t) => strtolower($t))
            ->all();

        if ($ada === []) {
            $this->error("Database '{$this->src}' tidak ditemukan / tidak bisa diakses user aplikasi.");

            return false;
        }

        $kurang = array_diff(self::TABEL_WAJIB, $ada);
        if ($kurang !== []) {
            $this->error("Database '{$this->src}' bukan dump CBT lama — tabel hilang: ".implode(', ', $kurang));

            return false;
        }

        return true;
    }

    /* -------------------------------------------------------- daftar mapel */

    /** Kumpulkan mapel di dump + contoh topik, supaya admin bisa mencocokkan sendiri. */
    protected function printMapel(): void
    {
        $rows = DB::select("
            SELECT t.id_mata_pelajaran            AS src_mapel,
                   COUNT(DISTINCT q.id)           AS soal,
                   COUNT(DISTINCT t.id)           AS topik,
                   GROUP_CONCAT(DISTINCT t.class ORDER BY t.class) AS tingkat,
                   SUBSTRING(GROUP_CONCAT(DISTINCT t.name ORDER BY t.id SEPARATOR ' / '), 1, 70) AS contoh_topik
            FROM `{$this->src}`.topicables tc
            JOIN `{$this->src}`.topics t    ON t.id = tc.topic_id
            JOIN `{$this->src}`.questions q ON q.id = tc.topicable_id
            WHERE q.deleted_at IS NULL
            GROUP BY t.id_mata_pelajaran
            ORDER BY soal DESC
        ");

        $this->info("Mapel yang ada di dump '{$this->src}':");
        $this->newLine();
        $this->table(
            ['id sumber', 'soal', 'topik', 'tingkat', 'contoh nama topik'],
            array_map(fn ($r) => [
                $r->src_mapel, $r->soal, $r->topik, $r->tingkat, $r->contoh_topik,
            ], $rows)
        );

        $this->newLine();
        $this->info('Mata pelajaran yang tersedia di Data Center:');
        $this->table(
            ['id', 'kode', 'nama'],
            MataPelajaran::orderBy('id')->get(['id', 'kode_mapel', 'nama_mapel'])
                ->map(fn ($m) => [$m->id, $m->kode_mapel, $m->nama_mapel])->all()
        );

        $this->newLine();
        $this->line('Cocokkan berdasarkan nama topik & isi soalnya, lalu tulis file JSON:');
        $this->line('  { "'.($rows[0]->src_mapel ?? 2).'": 5, "3": 6 }   <- "id sumber": id Data Center');
        $this->line('Mapel sumber yang tidak dicantumkan di file itu akan DILEWATI.');
    }

    /* ------------------------------------------------------- pemetaan input */

    /** @return array<int,int>|null */
    protected function bacaMapelMap(): ?array
    {
        $path = (string) $this->option('mapel-map');
        if ($path === '') {
            $this->error('--mapel-map wajib diisi.');
            $this->line("Jalankan dulu:  php artisan soal:import-legacy --source-db={$this->src} --print-mapel");

            return null;
        }
        if (! is_file($path)) {
            $this->error("File pemetaan tidak ditemukan: {$path}");

            return null;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw) || $raw === []) {
            $this->error("Isi {$path} bukan JSON objek yang valid. Contoh: ".'{"2": 5, "3": 6}');

            return null;
        }

        // Pastikan semua mapel tujuan benar-benar ada di Data Center — kalau
        // salah ketik, soal akan nyangkut tanpa mapel dan sulit ditelusuri.
        $mapelAda = MataPelajaran::pluck('nama_mapel', 'id')->all();
        $map = [];
        foreach ($raw as $srcId => $dstId) {
            if (! is_numeric($srcId) || ! is_numeric($dstId)) {
                $this->error("Pemetaan tidak valid: \"{$srcId}\": ".json_encode($dstId));

                return null;
            }
            if (! isset($mapelAda[(int) $dstId])) {
                $this->error("Mata pelajaran id {$dstId} tidak ada di Data Center (dari pemetaan \"{$srcId}\").");

                return null;
            }
            $map[(int) $srcId] = (int) $dstId;
        }

        return $map;
    }

    /**
     * Cocokkan question_types dump lama -> question_types aplikasi ini,
     * berdasarkan NAMA (bukan id), karena urutan id bisa berbeda antar dump.
     *
     * @return array<int,int>|null id jenis sumber -> id question_types di sini
     */
    protected function petakanJenis(): ?array
    {
        $slugKeId = QuestionType::pluck('id', 'slug')->all();
        $map = [];
        $gagal = [];

        foreach (DB::select("SELECT id, name FROM `{$this->src}`.question_types") as $t) {
            $nama = strtolower(trim(preg_replace('/\s+/', ' ', $t->name)));
            $slug = self::ALIAS_JENIS[$nama] ?? null;

            if ($slug === null || ! isset($slugKeId[$slug])) {
                $gagal[] = "{$t->name} (id {$t->id})";

                continue;
            }
            $map[(int) $t->id] = (int) $slugKeId[$slug];
        }

        if ($gagal !== []) {
            $this->error('Jenis soal berikut tidak dikenali: '.implode(', ', $gagal));
            $this->line('Tambahkan padanannya di ImportSoalLegacy::ALIAS_JENIS, atau buat jenisnya di tabel question_types.');

            return null;
        }

        return $map;
    }

    /* ------------------------------------------------------------ dry-run */

    /** @param array<int,int> $mapelMap @param array<int,int> $jenisMap */
    protected function laporkanRencana(array $mapelMap, array $jenisMap): int
    {
        $sudah = $this->sudahMasuk('question');
        $rows = [];
        $totalBaru = $totalLewat = 0;

        foreach ($this->ambilSoal($mapelMap) as $q) {
            $baru = ! isset($sudah[$q->src_id]);
            $baru ? $totalBaru++ : $totalLewat++;
            $nama = MataPelajaran::find($mapelMap[$q->src_mapel])?->nama_mapel ?? '?';
            $rows[$nama] ??= ['baru' => 0, 'lewat' => 0];
            $rows[$nama][$baru ? 'baru' : 'lewat']++;
        }

        $tidakDipetakan = DB::select("
            SELECT DISTINCT t.id_mata_pelajaran AS m
            FROM `{$this->src}`.topicables tc
            JOIN `{$this->src}`.topics t    ON t.id = tc.topic_id
            JOIN `{$this->src}`.questions q ON q.id = tc.topicable_id
            WHERE q.deleted_at IS NULL
              AND t.id_mata_pelajaran NOT IN (".implode(',', array_keys($mapelMap) ?: [0]).')
        ');

        $this->info("[dry-run] Rencana import batch '{$this->batch}' dari '{$this->src}':");
        $this->newLine();
        ksort($rows);
        $this->table(
            ['mata pelajaran', 'soal baru', 'sudah pernah masuk'],
            array_map(fn ($n, $r) => [$n, $r['baru'], $r['lewat']], array_keys($rows), $rows)
        );
        $this->line("  Total akan ditambahkan : {$totalBaru} soal");
        $this->line("  Dilewati (sudah ada)   : {$totalLewat} soal");

        if ($tidakDipetakan !== []) {
            $this->newLine();
            $this->warn('Mapel sumber ini punya soal tapi TIDAK ada di --mapel-map, jadi akan dilewati: '
                .implode(', ', array_map(fn ($r) => $r->m, $tidakDipetakan)));
        }

        $this->newLine();
        $this->line('Tidak ada yang ditulis. Hapus --dry-run untuk menjalankan.');

        return self::SUCCESS;
    }

    /* ------------------------------------------------------------- import */

    /** @param array<int,int> $mapelMap @param array<int,int> $jenisMap */
    protected function jalankanImport(array $mapelMap, array $jenisMap): int
    {
        $guruId = $this->option('guru-id') ? (int) $this->option('guru-id') : null;
        $now = now();

        $topikBaru = $soalBaru = $opsiBaru = 0;

        DB::transaction(function () use ($mapelMap, $jenisMap, $guruId, $now, &$topikBaru, &$soalBaru, &$opsiBaru) {
            /* --- 1. Topik: hanya yang benar-benar memuat soal aktif -------
             * deleted_at sengaja tidak diikutkan: di dump lama ada topik yang
             * ter-soft-delete tapi soalnya masih aktif dipakai — kalau ikut
             * terhapus, soalnya jadi yatim dan hilang dari daftar guru. */
            $petaTopik = $this->sudahMasuk('topic');

            foreach ($this->ambilTopik($mapelMap) as $t) {
                if (isset($petaTopik[$t->id])) {
                    continue;
                }
                $petaTopik[$t->id] = DB::table('topics')->insertGetId([
                    'topic' => $t->name,
                    'slug' => $t->slug,
                    'mata_pelajaran_id' => $mapelMap[$t->id_mata_pelajaran],
                    'tingkat' => $t->class,
                    'is_active' => $t->is_active,
                    'created_by_guru_id' => $guruId,
                    'created_at' => $t->created_at ?? $now,
                    'updated_at' => $t->updated_at ?? $now,
                ]);
                $topikBaru++;
            }
            $this->catat('topic', $petaTopik);

            /* --- 2. Soal: mapel & tingkat diturunkan dari topiknya --------
             * media_url/media_type dibuang — di dump lama isinya literal
             * 'url' / 'media url' (placeholder yang tidak pernah diisi). */
            $petaSoal = $this->sudahMasuk('question');
            $daftarSoal = $this->ambilSoal($mapelMap);
            $bar = $this->output->createProgressBar(count($daftarSoal));
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->setMessage('menulis soal');
            $bar->start();

            $slugJenis = QuestionType::pluck('slug', 'id')->all();
            $penjodohan = [];   // src_question_id => new_question_id
            // Hanya soal yang BARU ditulis di run ini. Opsi jawaban selalu
            // ikut soalnya, jadi soal yang sudah pernah masuk tidak boleh
            // diproses lagi — kalau tidak, opsinya tergandakan setiap run.
            $soalRunIni = [];

            foreach ($daftarSoal as $q) {
                $bar->advance();
                if (isset($petaSoal[$q->src_id])) {
                    continue;
                }

                $jenisId = $jenisMap[$q->question_type_id] ?? null;
                $slug = $jenisId ? ($slugJenis[$jenisId] ?? '') : '';

                $newId = DB::table('questions')->insertGetId([
                    'title' => mb_substr($q->title, 0, 191),
                    'question' => $q->name,
                    'question_type_id' => $jenisId,
                    'mata_pelajaran_id' => $mapelMap[$q->src_mapel],
                    'topic_id' => $petaTopik[$q->src_topic] ?? null,
                    'tingkat' => $q->class,
                    'tingkat_kesulitan' => 'sedang',
                    'is_active' => $q->is_active,
                    'created_by_guru_id' => $guruId,
                    'created_at' => $q->created_at ?? $now,
                    'updated_at' => $q->updated_at ?? $now,
                ]);
                $petaSoal[$q->src_id] = $newId;
                $soalRunIni[$q->src_id] = $newId;
                $soalBaru++;

                if ($slug === 'penjodohan') {
                    $penjodohan[$q->src_id] = $newId;
                }
            }
            $bar->setMessage('selesai');
            $bar->finish();
            $this->newLine(2);
            $this->catat('question', $petaSoal);

            /* --- 3. Opsi jawaban ------------------------------------------
             * Fill the Blank dilewati: di skema ini jawabannya bukan baris
             * opsi, melainkan kolom questions.correct_answer_text. */
            $opsiBaru = $this->tulisOpsi($soalRunIni, $jenisMap, $now, $penjodohan);

            /* --- 4. Fill the Blank: opsi is_correct -> correct_answer_text
             * Sengaja hanya soal baru: kalau guru sudah membetulkan kuncinya
             * lewat aplikasi, menjalankan ulang command tidak boleh menimpanya. */
            $this->tulisKunciIsian($soalRunIni, $jenisMap);

            /* --- 5. Penjodohan: matching_answers -> pair_group ------------- */
            $this->tulisPasangan($penjodohan);

            /* --- 6. Soal cacat (tanpa kunci) dinonaktifkan ----------------- */
            $this->nonaktifkanTanpaKunci($soalRunIni);

            /* --- 7. Rapikan tingkat di luar nalar (bug entri data lama) ---- */
            if ($this->option('max-tingkat')) {
                $this->rapikanTingkat((int) $this->option('max-tingkat'), $petaTopik);
            }
        });

        $this->info("Import batch '{$this->batch}' selesai.");
        $this->table(['', 'jumlah'], [
            ['Topik ditambahkan', $topikBaru],
            ['Soal ditambahkan', $soalBaru],
            ['Opsi ditambahkan', $opsiBaru],
        ]);

        $nonaktif = DB::table('questions')
            ->join('legacy_soal_imports as m', function ($j) {
                $j->on('m.new_id', '=', 'questions.id')->where('m.jenis', 'question');
            })
            ->where('m.batch', $this->batch)->where('questions.is_active', false)->count();

        if ($nonaktif > 0) {
            $this->warn("{$nonaktif} soal dinonaktifkan karena tidak punya kunci jawaban di dump asalnya.");
            $this->line('Soalnya tetap tersimpan supaya bisa dilengkapi guru, tapi tidak akan terpilih ke paket ujian.');
        }

        $gambar = DB::table('questions')
            ->join('legacy_soal_imports as m', function ($j) {
                $j->on('m.new_id', '=', 'questions.id')->where('m.jenis', 'question');
            })
            ->where('m.batch', $this->batch)
            ->where('questions.question', 'like', '%storage/%')->count();

        if ($gambar > 0) {
            $this->newLine();
            $this->warn("{$gambar} soal memuat <img> yang menunjuk ke storage server LAMA.");
            $this->line('File gambarnya tidak ikut di dump SQL — salin folder storage/app/public dari server lama,');
            $this->line('atau jalankan: php artisan soal:localize-images (untuk yang sumbernya URL eksternal).');
        }

        return self::SUCCESS;
    }

    /* --------------------------------------------------------- sub-langkah */

    /**
     * @param  array<int,int>  $soalBaru  HANYA soal yang baru ditulis run ini
     * @param  array<int,int>  $jenisMap
     * @param  array<int,int>  $penjodohan  src_question_id => new_question_id
     */
    protected function tulisOpsi(array $soalBaru, array $jenisMap, $now, array $penjodohan): int
    {
        if ($soalBaru === []) {
            return 0;
        }
        $idIsian = $this->idJenis($jenisMap, 'fill-blank');
        $buffer = [];
        $petaOpsi = [];
        $jumlah = 0;

        $urut = [];   // src_question_id => nomor urut opsi berikutnya

        $q = DB::table("{$this->src}.question_options as o")
            ->join("{$this->src}.questions as q", 'q.id', '=', 'o.question_id')
            ->whereNull('q.deleted_at')
            ->whereNull('o.deleted_at')
            ->when($idIsian !== null, fn ($b) => $b->where('q.question_type_id', '<>', $idIsian))
            ->orderBy('o.question_id')->orderBy('o.id')
            ->select('o.id', 'o.question_id', 'o.name', 'o.is_correct', 'o.is_left_side',
                'o.created_at', 'o.updated_at');

        foreach ($q->cursor() as $o) {
            if (! isset($soalBaru[$o->question_id])) {
                continue;
            }
            $urut[$o->question_id] = ($urut[$o->question_id] ?? -1) + 1;

            $baris = [
                'question_id' => $soalBaru[$o->question_id],
                'option_text' => $o->name,
                'is_correct' => $o->is_correct,
                'order' => min($urut[$o->question_id], 255),
                'is_left_side' => $o->is_left_side,
                'created_at' => $o->created_at ?? $now,
                'updated_at' => $o->updated_at ?? $now,
            ];

            // Opsi penjodohan perlu ditelusuri satu per satu karena id barunya
            // dipakai untuk menyusun pair_group dari tabel matching_answers.
            if (isset($penjodohan[$o->question_id])) {
                $petaOpsi[$o->id] = DB::table('question_options')->insertGetId($baris);
                $jumlah++;

                continue;
            }

            $buffer[$o->id] = $baris;
            if (count($buffer) >= 1000) {
                DB::table('question_options')->insert(array_values($buffer));
                $jumlah += count($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            DB::table('question_options')->insert(array_values($buffer));
            $jumlah += count($buffer);
        }

        // Hanya opsi penjodohan yang perlu dicatat petanya; opsi biasa tidak
        // dirujuk apa pun sehingga tidak perlu memenuhi tabel jejak.
        $this->catat('option', $petaOpsi);

        return $jumlah;
    }

    /** @param array<int,int> $petaSoal @param array<int,int> $jenisMap */
    protected function tulisKunciIsian(array $petaSoal, array $jenisMap): void
    {
        $idIsian = $this->idJenis($jenisMap, 'fill-blank');
        if ($idIsian === null) {
            return;
        }

        $rows = DB::select("
            SELECT o.question_id,
                   GROUP_CONCAT(NULLIF(TRIM(REGEXP_REPLACE(o.name, '<[^>]*>', '')), '')
                                ORDER BY o.id SEPARATOR '|') AS kunci
            FROM `{$this->src}`.question_options o
            JOIN `{$this->src}`.questions q ON q.id = o.question_id
            WHERE q.question_type_id = ?
              AND q.deleted_at IS NULL AND o.deleted_at IS NULL AND o.is_correct = 1
            GROUP BY o.question_id
        ", [$idIsian]);

        foreach ($rows as $r) {
            if (isset($petaSoal[$r->question_id]) && $r->kunci !== null && $r->kunci !== '') {
                DB::table('questions')->where('id', $petaSoal[$r->question_id])
                    ->update(['correct_answer_text' => $r->kunci]);
            }
        }
    }

    /** @param array<int,int> $penjodohan src_question_id => new_question_id */
    protected function tulisPasangan(array $penjodohan): void
    {
        if ($penjodohan === []
            || ! $this->punyaTabel('matching_answers')) {
            return;
        }

        $petaOpsi = $this->sudahMasuk('option');
        $nomor = [];   // new_question_id => nomor pasangan berikutnya

        $rows = DB::select("
            SELECT ma.left_option_id, ma.right_option_id, o.question_id
            FROM `{$this->src}`.matching_answers ma
            JOIN `{$this->src}`.question_options o ON o.id = ma.left_option_id
            ORDER BY o.question_id, ma.id
        ");

        foreach ($rows as $r) {
            $kiri = $petaOpsi[$r->left_option_id] ?? null;
            $kanan = $petaOpsi[$r->right_option_id] ?? null;
            if ($kiri === null || $kanan === null) {
                continue;
            }
            $grup = $nomor[$r->question_id] = ($nomor[$r->question_id] ?? 0) + 1;

            DB::table('question_options')->where('id', $kiri)
                ->update(['pair_group' => $grup, 'is_left_side' => true]);
            DB::table('question_options')->where('id', $kanan)
                ->update(['pair_group' => $grup, 'is_left_side' => false]);
        }
    }

    /**
     * Soal tanpa kunci jawaban tetap diimpor (supaya isinya tidak hilang dan
     * bisa dilengkapi guru) tapi dinonaktifkan, agar tidak ikut terpilih ke
     * paket ujian dan membuat semua siswa otomatis salah.
     *
     * @param  array<int,int>  $petaSoal
     */
    protected function nonaktifkanTanpaKunci(array $petaSoal): void
    {
        if ($petaSoal === []) {
            return;
        }
        $isian = QuestionType::where('slug', 'fill-blank')->value('id');

        foreach (array_chunk(array_values($petaSoal), 2000) as $ids) {
            DB::table('questions')->whereIn('id', $ids)
                ->where(function ($w) use ($isian) {
                    $w->where(function ($x) use ($isian) {
                        $x->where('question_type_id', $isian)
                            ->where(fn ($y) => $y->whereNull('correct_answer_text')
                                ->orWhere('correct_answer_text', ''));
                    })->orWhere(function ($x) use ($isian) {
                        $x->where('question_type_id', '<>', $isian)
                            ->whereNotExists(fn ($s) => $s->from('question_options')
                                ->whereColumn('question_options.question_id', 'questions.id')
                                ->where('is_correct', true));
                    });
                })
                ->update(['is_active' => false]);
        }
    }

    /**
     * Bug entri data yang lazim di aplikasi lama: satu topik terduplikasi
     * dengan kolom `class` naik terus (mis. 10,11,...,17 padahal SMP hanya
     * sampai 9). Soalnya dipindahkan ke topik senama & semapel dengan tingkat
     * valid TERBESAR, lalu topik duplikat yang jadi kosong dibuang.
     *
     * @param  array<int,int>  $petaTopik
     */
    protected function rapikanTingkat(int $maks, array $petaTopik): void
    {
        $ids = array_values($petaTopik);
        if ($ids === []) {
            return;
        }

        $rusak = DB::table('topics')->whereIn('id', $ids)->where('tingkat', '>', $maks)->get();
        $dipindah = $dibuang = 0;

        foreach ($rusak as $bad) {
            $good = DB::table('topics')
                ->whereIn('id', $ids)
                ->where('topic', $bad->topic)
                ->where('mata_pelajaran_id', $bad->mata_pelajaran_id)
                ->where('tingkat', '<=', $maks)
                ->orderByDesc('tingkat')
                ->first();

            if ($good) {
                $dipindah += DB::table('questions')->where('topic_id', $bad->id)
                    ->update(['topic_id' => $good->id, 'tingkat' => $good->tingkat]);

                if (DB::table('questions')->where('topic_id', $bad->id)->doesntExist()) {
                    DB::table('topics')->where('id', $bad->id)->delete();
                    // Jejaknya diarahkan ke topik hasil penggabungan, BUKAN
                    // dihapus — kalau dihapus, command dijalankan ulang akan
                    // mengira topik ini belum pernah masuk lalu membuatnya lagi.
                    DB::table('legacy_soal_imports')->where('batch', $this->batch)
                        ->where('jenis', 'topic')->where('new_id', $bad->id)
                        ->update(['new_id' => $good->id]);
                    $dibuang++;
                }

                continue;
            }

            // Tidak ada topik senama yang tingkatnya wajar → kosongkan saja,
            // lebih baik tanpa tingkat daripada tampil di kelas yang salah.
            DB::table('topics')->where('id', $bad->id)->update(['tingkat' => null]);
            DB::table('questions')->where('topic_id', $bad->id)->update(['tingkat' => null]);
        }

        if ($dipindah > 0 || $dibuang > 0) {
            $this->line("Tingkat di atas {$maks} dirapikan: {$dipindah} soal dipindah, {$dibuang} topik duplikat dibuang.");
        }
    }

    /* ------------------------------------------------------------ rollback */

    protected function rollback(): int
    {
        $jml = DB::table('legacy_soal_imports')->where('batch', $this->batch)->count();
        if ($jml === 0) {
            $this->warn("Tidak ada jejak import untuk batch '{$this->batch}'.");

            return self::SUCCESS;
        }

        $soal = DB::table('legacy_soal_imports')->where('batch', $this->batch)
            ->where('jenis', 'question')->pluck('new_id');
        $topik = DB::table('legacy_soal_imports')->where('batch', $this->batch)
            ->where('jenis', 'topic')->pluck('new_id');

        if (! $this->confirm("Hapus {$soal->count()} soal & {$topik->count()} topik hasil batch '{$this->batch}'?", false)) {
            return self::SUCCESS;
        }

        DB::transaction(function () use ($soal, $topik) {
            // question_options & quiz_questions ikut terhapus lewat cascade FK.
            foreach ($soal->chunk(2000) as $c) {
                DB::table('questions')->whereIn('id', $c->all())->delete();
            }
            foreach ($topik->chunk(2000) as $c) {
                DB::table('topics')->whereIn('id', $c->all())->delete();
            }
            DB::table('legacy_soal_imports')->where('batch', $this->batch)->delete();
        });

        $this->info("Batch '{$this->batch}' dibatalkan.");

        return self::SUCCESS;
    }

    /* -------------------------------------------------------------- utilitas */

    /** Soal aktif di dump, lengkap dengan mapel & tingkat dari topiknya. */
    protected function ambilSoal(array $mapelMap): array
    {
        if ($mapelMap === []) {
            return [];
        }

        return DB::select("
            SELECT q.id AS src_id, q.title, q.name, q.question_type_id, q.is_active,
                   q.created_at, q.updated_at,
                   t.id AS src_topic, t.class, t.id_mata_pelajaran AS src_mapel
            FROM `{$this->src}`.questions q
            JOIN `{$this->src}`.topicables tc ON tc.topicable_id = q.id
            JOIN `{$this->src}`.topics t      ON t.id = tc.topic_id
            WHERE q.deleted_at IS NULL
              AND t.id_mata_pelajaran IN (".implode(',', array_keys($mapelMap)).')
            ORDER BY q.id
        ');
    }

    /** Topik yang benar-benar memuat soal aktif — sisanya cuma jadi sampah. */
    protected function ambilTopik(array $mapelMap): array
    {
        if ($mapelMap === []) {
            return [];
        }

        return DB::select("
            SELECT t.id, t.name, t.slug, t.class, t.is_active,
                   t.id_mata_pelajaran, t.created_at, t.updated_at
            FROM `{$this->src}`.topics t
            WHERE t.id_mata_pelajaran IN (".implode(',', array_keys($mapelMap)).')
              AND EXISTS (
                    SELECT 1 FROM `'.$this->src.'`.topicables tc
                    JOIN `'.$this->src.'`.questions q ON q.id = tc.topicable_id
                    WHERE tc.topic_id = t.id AND q.deleted_at IS NULL
              )
            ORDER BY t.id
        ');
    }

    /** @return array<int,int> src_id => new_id yang sudah pernah diimpor */
    protected function sudahMasuk(string $jenis): array
    {
        return DB::table('legacy_soal_imports')
            ->where('batch', $this->batch)->where('jenis', $jenis)
            ->pluck('new_id', 'src_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @param array<int,int> $peta src_id => new_id */
    protected function catat(string $jenis, array $peta): void
    {
        $sudah = $this->sudahMasuk($jenis);
        $baru = array_diff_key($peta, $sudah);
        $now = now();

        foreach (array_chunk($baru, 1000, true) as $chunk) {
            DB::table('legacy_soal_imports')->insert(array_map(
                fn ($srcId, $newId) => [
                    'batch' => $this->batch, 'jenis' => $jenis,
                    'src_id' => $srcId, 'new_id' => $newId,
                    'created_at' => $now, 'updated_at' => $now,
                ],
                array_keys($chunk), $chunk
            ));
        }
    }

    /** id jenis di DUMP untuk slug tertentu di aplikasi ini. */
    protected function idJenis(array $jenisMap, string $slug): ?int
    {
        $target = QuestionType::where('slug', $slug)->value('id');
        if ($target === null) {
            return null;
        }
        $src = array_search((int) $target, $jenisMap, true);

        return $src === false ? null : (int) $src;
    }

    protected function punyaTabel(string $tabel): bool
    {
        return DB::table('information_schema.tables')
            ->where('table_schema', $this->src)->where('table_name', $tabel)->exists();
    }
}
