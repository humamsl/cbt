<?php

namespace App\Http\Controllers;

use App\Services\Backup\BackupService;
use Illuminate\Http\Request;

class BackupController extends Controller
{
    public function download(Request $r, BackupService $svc)
    {
        $modules = (array) $r->input('modules', ['guru', 'siswa', 'bank-soal']);
        $modules = array_values(array_intersect($modules, ['guru', 'siswa', 'bank-soal']));
        if (empty($modules)) {
            return back()->with('error', 'Pilih minimal 1 modul untuk di-backup.');
        }
        return $svc->downloadZip($modules);
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
