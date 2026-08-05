<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kebijakan;
use App\Models\KasTransaksi;
use App\Models\Pinjaman;
use App\Models\Simpanan;
use Illuminate\Http\Request;

class KasController extends Controller
{
    // GET /api/admin/kas -> riwayat pengeluaran/pemasukan kas + saldo kas saat ini
    public function index()
    {
        $totalSimpanan = Simpanan::where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah')
            - Simpanan::where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
        $totalPinjamanAktif = Pinjaman::where('status', 'DISETUJUI')->sum('jumlah');
        $totalKasKeluar = KasTransaksi::where('tipe', 'KELUAR')->sum('jumlah');
        $totalKasMasuk = KasTransaksi::where('tipe', 'MASUK')->sum('jumlah');

        $saldoKas = $totalSimpanan - $totalPinjamanAktif + $totalKasMasuk - $totalKasKeluar;

        return response()->json([
            'saldo_kas' => $saldoKas,
            'riwayat' => KasTransaksi::with('dicatatOleh:id,nama')->latest()->take(30)->get(),
        ]);
    }

    // POST /api/admin/kas/tarik -> Bendahara/Ketua tarik kas koperasi untuk kebutuhan operasional
    public function tarik(Request $request)
    {
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:1',
            'catatan' => 'required|string|max:500',
        ]);

        $kas = KasTransaksi::create([
            ...$validated,
            'tipe' => 'KELUAR',
            'tanggal' => now()->toDateString(),
            'dicatat_oleh' => $request->user()->id,
        ]);

        return response()->json($kas, 201);
    }
}
