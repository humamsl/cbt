<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function download(Request $r, BackupService $svc)
    {
        // Guru & siswa sekarang dikelola di aplikasi Data Center (CBT hanya
        // menyimpan cache) — backup data induk mereka dilakukan dari sana,
        // bukan dari CBT. Hanya Bank Soal (data milik CBT) yang di-backup di sini.
        return $svc->downloadZip(['bank-soal']);
    }

    public function restore(Request $r, BackupService $svc)
    {
        $r->validate(['file' => 'required|file|mimes:zip|max:51200']); // 50 MB
        try {
            $summary = $svc->restoreZip($r->file('file'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Restore gagal: '.$e->getMessage());
        }
        return back()
            ->with('success', 'Restore selesai.')
            ->with('restoreSummary', $summary);
    }
}
