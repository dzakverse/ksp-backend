<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pinjaman;
use Illuminate\Http\Request;

class PinjamanController extends Controller
{
    // GET /api/pinjaman -> punya user yang login sendiri (pages/pinjaman.jsx)
    public function index(Request $request)
    {
        $riwayat = $request->user()->pinjamans()->orderByDesc('created_at')->get();

        // Pinjaman "aktif" = yang sudah DISETUJUI dan masih ada sisa cicilan belum lunas
        $aktif = $riwayat->firstWhere('status', 'DISETUJUI');
        $pinjamanAktif = null;

        if ($aktif) {
            $aktif->load('cicilans');
            $totalDibayar = $aktif->cicilans->where('status', 'LUNAS')->sum('jumlah');
            $angsuranPerBulan = $aktif->tenor_bulan > 0 ? $aktif->jumlah / $aktif->tenor_bulan : 0;

            $pinjamanAktif = [
                'kode' => $aktif->kode,
                'jumlah' => $aktif->jumlah,
                'tenor_bulan' => $aktif->tenor_bulan,
                'angsuran_per_bulan' => round($angsuranPerBulan),
                'sisa_pinjaman' => $aktif->jumlah - $totalDibayar,
            ];
        }

        return response()->json([
            'pinjaman_aktif' => $pinjamanAktif,
            'total_pengajuan' => $riwayat->count(),
            'riwayat' => $riwayat,
        ]);
    }

    // POST /api/pinjaman -> pengajuan baru (pages/ajukan.jsx)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:100000',
            'tenor_bulan' => 'required|integer|min:1|max:36',
            'alasan' => 'nullable|string',
        ]);

        $pinjaman = $request->user()->pinjamans()->create([
            ...$validated,
            'kode' => 'LN-' . now()->format('Y') . '-' . str_pad(Pinjaman::count() + 1, 3, '0', STR_PAD_LEFT),
            'status' => 'MENUNGGU',
        ]);

        return response()->json($pinjaman, 201);
    }

    // GET /api/admin/pinjaman -> semua pengajuan, buat Bendahara (pages/admin/VerifikasiPinjaman.jsx)
    public function indexAll()
    {
        return response()->json(
            Pinjaman::with('user')->orderByDesc('created_at')->get()
        );
    }

    // POST /api/admin/pinjaman/{id}/verifikasi -> Bendahara verifikasi (VerifikasiDetail.jsx)
    public function verifikasi(Request $request, Pinjaman $pinjaman)
    {
        $validated = $request->validate([
            'status' => 'required|in:DISETUJUI_BENDAHARA,DITOLAK',
            'catatan_verifikasi' => 'nullable|string',
        ]);

        $pinjaman->update([
            ...$validated,
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        return response()->json($pinjaman);
    }

    // POST /api/ketua/pinjaman/{id}/persetujuan -> Ketua approve final (PersetujuanPinjaman.jsx)
    public function persetujuanKetua(Request $request, Pinjaman $pinjaman)
    {
        $validated = $request->validate([
            'status' => 'required|in:DISETUJUI,DITOLAK',
            'catatan_verifikasi' => 'nullable|string',
        ]);

        $pinjaman->update($validated);

        return response()->json($pinjaman);
    }
}
