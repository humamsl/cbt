<?php

namespace App\Console\Commands;

use App\Models\MataPelajaran;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use ZipArchive;

/**
 * Isi ulang mata pelajaran pada soal yang masuk "tanpa mapel".
 *
 * Kenapa ini terjadi: restore/import mencocokkan mata pelajaran lewat KODE
 * MAPEL pada saat proses berjalan. Kalau kodenya belum ada di Data Center saat
 * itu, soalnya tetap masuk tapi `mata_pelajaran_id` dibiarkan kosong. Membuat
 * mapelnya belakangan tidak menyambung sendiri ke soal yang terlanjur masuk.
 *
 * JANGAN diperbaiki dengan mengupload ulang berkas backup-nya: kunci pengenal
 * soal saat restore ikut menyertakan mapel, jadi soal bermapel kosong tidak
 * akan dikenali sebagai soal yang sama — hasilnya soal tergandakan, bukan
 * diperbaiki.
 *
 * DUA CARA
 *
 *  a) Dari berkas backup (paling tepat — kode mapel aslinya ada di situ):
 *
 *       php artisan soal:perbaiki-mapel --file=bank-soal-smpn218.zip --dry-run
 *       php artisan soal:perbaiki-mapel --file=bank-soal-smpn218.zip
 *
 *  b) Dari topik soal, kalau topiknya sudah punya mapel yang benar:
 *
 *       php artisan soal:perbaiki-mapel --dari-topik
 *
 * Tanpa opsi apa pun, command hanya MELAPORKAN keadaan sekarang — soal tanpa
 * mapel dikelompokkan per topik, supaya ketahuan mapel apa saja yang masih
 * perlu dibuat di Data Center.
 */
class PerbaikiMapelSoal extends Command
{
    protected $signature = 'soal:perbaiki-mapel
        {--file= : Berkas backup .zip/.json sumber soal tersebut}
        {--dari-topik : Ambil mapel dari topik soal, bukan dari berkas}
        {--timpa : Perbaiki juga soal yang mapelnya sudah terisi}
        {--dry-run : Tampilkan yang akan diubah tanpa menyimpan}';

    protected $description = 'Isi mata pelajaran pada soal yang masuk tanpa mapel (setelah mapelnya dibuat belakangan)';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        if ($this->option('file')) {
            return $this->dariBerkas((string) $this->option('file'), $dry);
        }

        if ($this->option('dari-topik')) {
            return $this->dariTopik($dry);
        }

        return $this->laporkan();
    }

    /* ------------------------------------------------------------ laporan */

    /** Tanpa opsi: cuma tunjukkan keadaan sekarang. */
    protected function laporkan(): int
    {
        $tanpaMapel = DB::table('questions')->whereNull('mata_pelajaran_id')->whereNull('deleted_at')->count();

        if ($tanpaMapel === 0) {
            $this->info('Semua soal sudah punya mata pelajaran.');

            return self::SUCCESS;
        }

        $this->warn("{$tanpaMapel} soal belum punya mata pelajaran.");
        $this->newLine();

        $rows = DB::table('questions as q')
            ->leftJoin('topics as t', 't.id', '=', 'q.topic_id')
            ->whereNull('q.mata_pelajaran_id')
            ->whereNull('q.deleted_at')
            ->selectRaw('COALESCE(t.topic, "(tanpa topik)") AS topik, q.tingkat,
                         COUNT(*) AS jml, MAX(t.mata_pelajaran_id) AS mapel_topik')
            ->groupBy('topik', 'q.tingkat', 't.mata_pelajaran_id')
            ->orderByDesc('jml')
            ->limit(40)
            ->get();

        $this->table(
            ['topik', 'tingkat', 'jumlah soal', 'mapel di topik'],
            $rows->map(fn ($r) => [
                mb_strimwidth($r->topik, 0, 48, '…'),
                $r->tingkat ?? '-',
                $r->jml,
                $r->mapel_topik ? (MataPelajaran::find($r->mapel_topik)?->nama_mapel ?? $r->mapel_topik) : '— kosong juga —',
            ])->all()
        );

        $adaMapelTopik = $rows->contains(fn ($r) => $r->mapel_topik !== null);

        $this->newLine();
        $this->line('Cara memperbaiki:');
        if ($adaMapelTopik) {
            $this->line('  php artisan soal:perbaiki-mapel --dari-topik --dry-run');
        }
        $this->line('  php artisan soal:perbaiki-mapel --file=berkas-backup.zip --dry-run');
        $this->newLine();
        $this->line('Pastikan dulu mapelnya sudah dibuat di Data Center. Jangan mengupload');
        $this->line('ulang berkas backup untuk memperbaiki ini — soalnya akan tergandakan.');

        return self::SUCCESS;
    }

    /* --------------------------------------------------------- dari berkas */

    protected function dariBerkas(string $path, bool $dry): int
    {
        if (! is_file($path)) {
            $this->error("Berkas tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        $raw = str_ends_with(strtolower($path), '.zip')
            ? $this->bacaZip($path)
            : file_get_contents($path);

        if ($raw === null) {
            $this->error('ZIP tidak berisi bank-soal.json.');

            return self::FAILURE;
        }

        $rows = json_decode($raw, true)['questions'] ?? [];
        if ($rows === []) {
            $this->error('Berkas tidak berisi daftar soal.');

            return self::FAILURE;
        }

        /* Kunci pencocokan sengaja TIDAK memakai mapel — justru mapel itulah
         * yang sedang diperbaiki, jadi nilainya pasti berbeda antara berkas
         * dan database. */
        $petaKode = [];
        $bentrok = [];
        foreach ($rows as $r) {
            $k = $this->kunci($r['title'] ?? '', $r['question'] ?? '', $r['tingkat'] ?? null);
            $kode = $r['mata_pelajaran'] ?? null;
            if ($kode === null) {
                continue;
            }
            if (isset($petaKode[$k]) && $petaKode[$k] !== $kode) {
                $bentrok[$k] = true;   // isi sama tapi diklaim 2 mapel berbeda
            }
            $petaKode[$k] = $kode;
        }

        $mapelMap = MataPelajaran::pluck('id', 'kode_mapel')->all();
        $terpakai = [];
        $kodeHilang = [];
        $takKetemu = 0;
        $diperbaiki = 0;
        $topikDiperbaiki = 0;

        $q = DB::table('questions')->whereNull('deleted_at')
            ->when(! $this->option('timpa'), fn ($b) => $b->whereNull('mata_pelajaran_id'))
            ->select('id', 'title', 'question', 'tingkat', 'topic_id');

        $total = (clone $q)->count();
        if ($total === 0) {
            $this->info('Tidak ada soal yang perlu diperbaiki.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        DB::transaction(function () use (
            $q, $petaKode, $bentrok, $mapelMap, $dry, $bar,
            &$terpakai, &$kodeHilang, &$takKetemu, &$diperbaiki, &$topikDiperbaiki
        ) {
            foreach ($q->orderBy('id')->cursor() as $row) {
                $bar->advance();
                $k = $this->kunci($row->title, $row->question, $row->tingkat);

                if (! isset($petaKode[$k]) || isset($bentrok[$k])) {
                    $takKetemu++;

                    continue;
                }

                $kode = $petaKode[$k];
                if (! isset($mapelMap[$kode])) {
                    $kodeHilang[$kode] = ($kodeHilang[$kode] ?? 0) + 1;

                    continue;
                }

                $mapelId = $mapelMap[$kode];
                $terpakai[$kode] = ($terpakai[$kode] ?? 0) + 1;
                $diperbaiki++;

                if ($dry) {
                    continue;
                }

                DB::table('questions')->where('id', $row->id)
                    ->update(['mata_pelajaran_id' => $mapelId, 'updated_at' => now()]);

                // Topiknya ikut kosong karena sebab yang sama — kalau tidak
                // diisi, filter "mapel + topik" di Bank Soal tetap tidak nyambung.
                if ($row->topic_id) {
                    $affected = DB::table('topics')->where('id', $row->topic_id)
                        ->whereNull('mata_pelajaran_id')
                        ->update(['mata_pelajaran_id' => $mapelId, 'updated_at' => now()]);
                    $topikDiperbaiki += $affected;
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->hasil($dry, $diperbaiki, $topikDiperbaiki, $terpakai, $kodeHilang, $takKetemu, $mapelMap);

        return self::SUCCESS;
    }

    /* ---------------------------------------------------------- dari topik */

    protected function dariTopik(bool $dry): int
    {
        $q = DB::table('questions as q')
            ->join('topics as t', 't.id', '=', 'q.topic_id')
            ->whereNull('q.deleted_at')
            ->whereNotNull('t.mata_pelajaran_id')
            ->when(! $this->option('timpa'), fn ($b) => $b->whereNull('q.mata_pelajaran_id'));

        $rekap = (clone $q)->selectRaw('t.mata_pelajaran_id AS m, COUNT(*) AS c')
            ->groupBy('t.mata_pelajaran_id')->get();
        $total = $rekap->sum('c');

        if ($total === 0) {
            $this->info('Tidak ada soal yang bisa diperbaiki lewat topik.');
            $this->line('Topiknya kemungkinan ikut kosong mapelnya — pakai --file=berkas-backup.zip.');

            return self::SUCCESS;
        }

        $this->table(
            ['mata pelajaran', 'soal'],
            $rekap->map(fn ($r) => [MataPelajaran::find($r->m)?->nama_mapel ?? $r->m, $r->c])->all()
        );

        if ($dry) {
            $this->line("[dry-run] {$total} soal akan diisi mapelnya dari topik. Tidak ada yang disimpan.");

            return self::SUCCESS;
        }

        DB::table('questions')
            ->join('topics', 'topics.id', '=', 'questions.topic_id')
            ->whereNull('questions.deleted_at')
            ->whereNotNull('topics.mata_pelajaran_id')
            ->when(! $this->option('timpa'), fn ($b) => $b->whereNull('questions.mata_pelajaran_id'))
            ->update([
                'questions.mata_pelajaran_id' => DB::raw('topics.mata_pelajaran_id'),
                'questions.updated_at' => now(),
            ]);

        $this->info("{$total} soal diisi mata pelajarannya dari topik.");

        return self::SUCCESS;
    }

    /* -------------------------------------------------------------- bantu */

    /**
     * @param  array<string,int>  $terpakai
     * @param  array<string,int>  $kodeHilang
     * @param  array<string,int>  $mapelMap
     */
    protected function hasil(bool $dry, int $diperbaiki, int $topik, array $terpakai, array $kodeHilang, int $takKetemu, array $mapelMap): void
    {
        if ($terpakai !== []) {
            ksort($terpakai);
            $this->table(
                ['kode', 'mata pelajaran', 'soal'],
                array_map(fn ($kode, $n) => [
                    $kode,
                    MataPelajaran::find($mapelMap[$kode])?->nama_mapel ?? '?',
                    $n,
                ], array_keys($terpakai), $terpakai)
            );
        }

        $awalan = $dry ? '[dry-run] ' : '';
        $this->info("{$awalan}{$diperbaiki} soal diisi mata pelajarannya.");
        if ($topik > 0) {
            $this->line("{$awalan}{$topik} topik ikut diisi mapelnya.");
        }

        if ($kodeHilang !== []) {
            $this->newLine();
            $this->warn('Kode mapel berikut ADA di berkas tapi BELUM dibuat di Data Center:');
            foreach ($kodeHilang as $kode => $n) {
                $this->line("  {$kode} — {$n} soal masih tanpa mapel");
            }
            $this->line('Buat mapelnya dengan kode persis seperti di atas, lalu jalankan lagi.');
        }

        if ($takKetemu > 0) {
            $this->newLine();
            $this->line("{$takKetemu} soal tidak ada padanannya di berkas (soal buatan sendiri / isinya sudah diedit) — dilewati.");
        }

        if ($dry) {
            $this->newLine();
            $this->line('Tidak ada yang disimpan. Hapus --dry-run untuk menjalankan.');
        }
    }

    protected function bacaZip(string $path): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }
        $isi = $zip->getFromName('bank-soal.json');
        $zip->close();

        return $isi === false ? null : $isi;
    }

    /** Sengaja tanpa mapel — mapel justru yang sedang diperbaiki. */
    protected function kunci(?string $judul, ?string $isi, $tingkat): string
    {
        $rapikan = fn ($s) => preg_replace('/\s+/u', ' ', trim((string) $s));

        return sha1($rapikan($judul).'|'.$rapikan($isi).'|'.($tingkat ?? ''));
    }
}
