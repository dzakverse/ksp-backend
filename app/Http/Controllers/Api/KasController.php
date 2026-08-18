<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KasTransaksi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        // Dibungkus transaction + lock semua baris kas_transaksis, supaya dua
        // penarikan kas yang nyaris bersamaan (mis. dua pengurus tarik kas di
        // waktu yang sama) tidak sama-sama lolos cek "kas cukup?" sebelum salah
        // satunya sempat commit. CATATAN: saldoSaatIni() juga menghitung dari
        // tabel simpanans & pinjamans, yang tidak ikut ter-lock di sini - jadi
        // ini melindungi dari race ANTAR penarikan kas, tapi belum 100% menutup
        // race antara penarikan kas vs pencairan pinjaman/simpanan yang terjadi
        // persis bersamaan. Untuk menutup itu sepenuhnya perlu mekanisme lock
        // terpusat (mis. tabel/baris lock khusus) di semua titik yang menyentuh
        // saldo kas - di luar cakupan perbaikan ini.
        $saldoKurang = null;

        $kas = DB::transaction(function () use ($request, $validated, &$saldoKurang) {
            KasTransaksi::lockForUpdate()->get();

            $saldoSaatIni = KasTransaksi::saldoSaatIni();
            if ($validated['jumlah'] > $saldoSaatIni) {
                $saldoKurang = $saldoSaatIni;
                return null;
            }

            return KasTransaksi::create([
                ...$validated,
                'tipe' => 'KELUAR',
                'tanggal' => now()->toDateString(),
                'dicatat_oleh' => $request->user()->id,
            ]);
        });

        if ($saldoKurang !== null) {
            return response()->json([
                'message' => 'Kas koperasi tidak mencukupi. Saldo kas saat ini hanya Rp ' . number_format($saldoKurang, 0, ',', '.') . '.',
            ], 422);
        }

        return response()->json($kas, 201);
    }
}