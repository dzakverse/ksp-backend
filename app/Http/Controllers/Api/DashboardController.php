<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // GET /api/dashboard -> pages/dashboard.jsx (Beranda)
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            // Hitung Sub Saldo Simpanan
            $totalPokok = $user->simpanans()->where('jenis', 'POKOK')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;
            $totalWajib = $user->simpanans()->where('jenis', 'WAJIB')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;
            $sukarelaSetor = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;
            $sukarelaTarik = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->sum('jumlah') ?? 0;
            $totalSukarela = $sukarelaSetor - $sukarelaTarik;

            // Gabungkan aktivitas simpanan
            $aktivitasSimpanan = $user->simpanans()->get()->map(function ($s) {
                // Pastikan format tanggal aman diparse
                $tgl = $s->tanggal ? Carbon::parse($s->tanggal)->toDateString() : date('Y-m-d');
                
                return [
                    'tanggal' => $tgl,
                    'deskripsi' => ucfirst(strtolower($s->tipe)) . ' Simpanan ' . ucfirst(strtolower($s->jenis))
                        . ($s->keterangan ? ' - ' . $s->keterangan : ''),
                    'kategori' => $s->jenis,
                    'jumlah' => (float) $s->jumlah,
                    'arah' => $s->tipe === 'SETOR' ? 'in' : 'out',
                    'status' => $s->status,
                ];
            });

            // Gabungkan aktivitas pinjaman
            $aktivitasPinjaman = $user->pinjamans()->get()->map(function ($p) {
                $tgl = $p->created_at ? Carbon::parse($p->created_at)->toDateString() : date('Y-m-d');

                return [
                    'tanggal' => $tgl,
                    'deskripsi' => 'Pengajuan Pinjaman #' . ($p->kode ?? $p->id),
                    'kategori' => 'PINJAMAN',
                    'jumlah' => (float) $p->jumlah,
                    'arah' => 'pending',
                    'status' => match ($p->status) {
                        'MENUNGGU' => 'Menunggu Verifikasi',
                        'DISETUJUI_BENDAHARA' => 'Menunggu Persetujuan Ketua',
                        'DISETUJUI' => 'Disetujui',
                        'DITOLAK' => 'Ditolak',
                        default => $p->status ?? 'Diproses',
                    },
                ];
            });

            // Urutkan & gabungkan
            $aktivitas = $aktivitasSimpanan->concat($aktivitasPinjaman)
                ->sortByDesc('tanggal')
                ->values()
                ->take(6);

            return response()->json([
                'profil' => [
                    'nama' => $user->nama,
                    'id_anggota' => $user->id_anggota,
                    'tanggal_bergabung' => $user->tanggal_bergabung,
                ],
                'total_simpanan' => (float) ($totalPokok + $totalWajib + $totalSukarela),
                'sub_saldo' => [
                    'pokok' => (float) $totalPokok,
                    'wajib' => (float) $totalWajib,
                    'sukarela' => (float) $totalSukarela,
                ],
                'aktivitas_terakhir' => $aktivitas,
            ]);

        } catch (\Exception $e) {
            // Tangkap exception agar terlihat detail error jika masih ada yang kurang
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }
}