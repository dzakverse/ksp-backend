<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasTransaksi;
use Illuminate\Http\Request;

class KasController extends Controller
{
    // GET /api/admin/kas -> riwayat pengeluaran/pemasukan kas + saldo kas saat ini
    public function index()
    {
        return response()->json([
            'saldo_kas' => KasTransaksi::saldoSaatIni(),
            'riwayat' => KasTransaksi::with('dicatatOleh:id,nama')->latest()->take(30)->get(),
        ]);
    }

    // GET /api/kas/saldo -> versi ringan buat Anggota (cek kas cukup sebelum ajukan pinjaman),
    // tanpa expose riwayat transaksi kas yang sifatnya administratif.
    public function saldo()
    {
        return response()->json([
            'saldo_kas' => KasTransaksi::saldoSaatIni(),
        ]);
    }

    // POST /api/admin/kas/tarik -> Bendahara/Ketua tarik kas koperasi untuk kebutuhan operasional
    public function tarik(Request $request)
    {
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:1',
            'catatan' => 'required|string|max:500',
        ]);

        $saldoSaatIni = KasTransaksi::saldoSaatIni();
        if ($validated['jumlah'] > $saldoSaatIni) {
            return response()->json([
                'message' => 'Kas koperasi tidak mencukupi. Saldo kas saat ini hanya Rp ' . number_format($saldoSaatIni, 0, ',', '.') . '.',
            ], 422);
        }

        $kas = KasTransaksi::create([
            ...$validated,
            'tipe' => 'KELUAR',
            'tanggal' => now()->toDateString(),
            'dicatat_oleh' => $request->user()->id,
        ]);

        return response()->json($kas, 201);
    }
}
