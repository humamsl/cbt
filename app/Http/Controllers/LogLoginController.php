<?php

namespace App\Http\Controllers;

use App\Models\LoginAttempt;
use Illuminate\Http\Request;

class LogLoginController extends Controller
{
    public function index(Request $r)
    {
        $items = LoginAttempt::query()
            ->when($r->q,       fn ($q) => $q->where('username', 'like', "%{$r->q}%"))
            ->when($r->guard,   fn ($q) => $q->where('guard', $r->guard))
            ->when($r->status,  function ($q) use ($r) {
                $q->where('success', $r->status === 'sukses');
            })
            ->when($r->device,  fn ($q) => $q->where('device_type', $r->device))
            ->when($r->tanggal, fn ($q) => $q->whereDate('created_at', $r->tanggal))
            ->latest()->paginate(30)->withQueryString();

        // Statistik ringkas
        $stat = [
            'total'   => LoginAttempt::count(),
            'sukses'  => LoginAttempt::where('success', true)->count(),
            'gagal'   => LoginAttempt::where('success', false)->count(),
            'hari_ini'=> LoginAttempt::whereDate('created_at', today())->count(),
        ];

        return view('log-login.index', compact('items', 'stat'));
    }
}
