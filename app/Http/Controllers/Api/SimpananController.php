<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SimpananController extends Controller
{
    // GET /api/simpanan -> data buat dashboard.jsx & simpananku.jsx
    public function index(Request $request)
    {
        $user = $request->user();

        $simpanans = $user->simpanans()->orderByDesc('tanggal')->get();

        $totalPokok = $user->simpanans()->where('jenis', 'POKOK')->where('tipe', 'SETOR')->sum('jumlah');
        $totalWajib = $user->simpanans()->where('jenis', 'WAJIB')->where('tipe', 'SETOR')->sum('jumlah');
        $totalSukarelaSetor = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->sum('jumlah');
        $totalSukarelaTarik = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->sum('jumlah');

        return response()->json([
            'total_pokok' => $totalPokok,
            'total_wajib' => $totalWajib,
            'total_sukarela' => $totalSukarelaSetor - $totalSukarelaTarik,
            'total_keseluruhan' => $totalPokok + $totalWajib + ($totalSukarelaSetor - $totalSukarelaTarik),
            'riwayat' => $simpanans,
        ]);
    }
}
