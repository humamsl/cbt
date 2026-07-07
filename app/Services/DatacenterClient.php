<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Klien HTTP ke aplikasi Data Center (provider data induk sekolah).
 *
 * CBT tidak lagi menyimpan password guru/siswa maupun mengelola data
 * kurikulum (mapel/rombel/tahun ajaran/sekolah) secara lokal — aplikasi
 * Data Center adalah pemilik data tersebut. Setiap login guru/siswa dan
 * ganti password diverifikasi lewat API ini (lihat AuthController &
 * ProfilController). Data referensi (mapel/rombel/tahun ajaran/dst) di-cache
 * di tabel lokal yang sama seperti sebelumnya, diisi lewat `datacenter:sync`
 * atau otomatis ter-upsert saat login (lihat AuthController::syncSiswa/syncGuru).
 */
class DatacenterClient
{
    protected string $baseUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.datacenter.api_url'), '/');
        $this->token = config('services.datacenter.token');
    }

    protected function http()
    {
        return Http::baseUrl($this->baseUrl)
            ->withToken((string) $this->token)
            ->acceptJson()
            ->timeout(10);
    }

    public function verifySiswa(string $nisn, string $password): Response
    {
        return $this->http()->post('/v1/auth/verify-siswa', [
            'username' => $nisn,
            'password' => $password,
        ]);
    }

    public function verifyGuru(string $nip, string $password): Response
    {
        return $this->http()->post('/v1/auth/verify-guru', [
            'username' => $nip,
            'password' => $password,
        ]);
    }

    public function changePasswordSiswa(string $nisn, string $currentPassword, string $newPassword): Response
    {
        return $this->http()->post('/v1/auth/change-password-siswa', [
            'username' => $nisn,
            'current_password' => $currentPassword,
            'password' => $newPassword,
        ]);
    }

    public function changePasswordGuru(string $nip, string $currentPassword, string $newPassword): Response
    {
        return $this->http()->post('/v1/auth/change-password-guru', [
            'username' => $nip,
            'current_password' => $currentPassword,
            'password' => $newPassword,
        ]);
    }

    public function tahunAjaran(): array
    {
        return (array) $this->http()->get('/v1/tahun-ajaran')->json('data', []);
    }

    public function jurusan(): array
    {
        return (array) $this->http()->get('/v1/jurusan')->json('data', []);
    }

    public function mataPelajaran(): array
    {
        return (array) $this->http()->get('/v1/mata-pelajaran')->json('data', []);
    }

    public function rombel(): array
    {
        return (array) $this->http()->get('/v1/rombel')->json('data', []);
    }

    public function tingkatKelas(): array
    {
        return (array) $this->http()->get('/v1/tingkat-kelas')->json('data', []);
    }

    public function sekolah(): ?array
    {
        return $this->http()->get('/v1/sekolah')->json('data');
    }

    public function guruMapel(): array
    {
        return (array) $this->http()->get('/v1/guru-mapel')->json('data', []);
    }

    /** Semua siswa (menyertakan rombel_sekarang) — loop seluruh halaman paginasi. */
    public function allSiswa(): array
    {
        return $this->fetchAllPaginated('/v1/siswa');
    }

    /** Semua guru (menyertakan guru_mapel) — loop seluruh halaman paginasi. */
    public function allGuru(): array
    {
        return $this->fetchAllPaginated('/v1/guru');
    }

    /**
     * Ambil seluruh data dari endpoint yang paginated (respons Laravel paginator:
     * { data, current_page, last_page, ... }) dengan menelusuri semua halaman.
     */
    protected function fetchAllPaginated(string $path, int $perPage = 200): array
    {
        $all = [];
        $page = 1;
        do {
            $res  = (array) $this->http()->get($path, ['per_page' => $perPage, 'page' => $page])->json();
            $data = $res['data'] ?? [];
            $all  = array_merge($all, $data);
            $last = (int) ($res['last_page'] ?? 1);
            $page++;
        } while ($page <= $last && ! empty($data));

        return $all;
    }
}
