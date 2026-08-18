<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Simpanan;
use App\Models\Pinjaman;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard -> Dashboard Anggota
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Hitung Sub Saldo Simpanan
            $saldo = Simpanan::breakdownSaldo($user->id);
            $totalPokok = $saldo['pokok'];
            $totalWajib = $saldo['wajib'];
            $totalSukarela = $saldo['sukarela'];

            // Gabungkan aktivitas simpanan
            $aktivitasSimpanan = $user->simpanans()->get()->map(function ($s) {
                $tgl = $s->tanggal ? Carbon::parse($s->tanggal)->toDateString() : date('Y-m-d');
                
                return [
                    'tanggal' => $tgl,
                    'deskripsi' => ucfirst(strtolower($s->tipe)) . ' Simpanan ' . ucfirst(strtolower($s->jenis))
                        . ($s->keterangan ? ' - ' . $s->keterangan : ''),
                    'kategori' => $s->jenis,
                    'jumlah' => (float) $s->jumlah,
                    // 'arah' = arah uang saja (dipakai utk warna & tanda +/- nominal),
                    // konsisten dengan halaman Simpanan Saya. Warna badge status
                    // ditentukan terpisah di frontend dari teks 'status', bukan dari sini.
                    'arah' => $s->tipe === 'SETOR' ? 'in' : 'out',
                    'status' => $s->status ?? 'BERHASIL',
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
                    // Nominal pinjaman ditampilkan netral (tanpa warna/tanda +/-),
                    // konsisten dengan tabel Riwayat Pinjaman di halaman Pinjaman.
                    // Warna badge status ditentukan terpisah di frontend dari teks 'status'.
                    'arah' => 'netral',
                    'status' => match ($p->status) {
                        'MENUNGGU' => 'Menunggu Verifikasi',
                        'DISETUJUI_BENDAHARA' => 'Menunggu Persetujuan Ketua',
                        'DISETUJUI' => 'Disetujui',
                        'DITOLAK' => 'Ditolak',
                        default => $p->status ?? 'Diproses',
                    },
                ];
            });

            // Urutkan & gabungkan aktivitas
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
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * GET /api/admin/dashboard -> Dashboard Admin / Bendahara / Ketua
     */
public function adminIndex(Request $request)
    {
        try {
            // 1. Total Simpanan Anggota berdasarkan jenis
            $saldo = Simpanan::breakdownSaldoSemua();
            $totalPokok = $saldo['pokok'];
            $totalWajib = $saldo['wajib'];
            $totalSukarela = $saldo['sukarela'];
            $totalSimpananAnggota = $saldo['total'];

            // 2. Total Pinjaman Aktif (Sisa pokok pinjaman yang disetujui / belum lunas)
            $totalPinjamanAktif = Pinjaman::where('status', 'DISETUJUI')
                ->sum('jumlah') ?? 0;

            // 3. Total Kas Koperasi (Simpanan Anggota - Pinjaman Aktif)
            $totalKasKeluar = \App\Models\KasTransaksi::where('tipe', 'KELUAR')->sum('jumlah');
            $totalKasMasuk = \App\Models\KasTransaksi::where('tipe', 'MASUK')->sum('jumlah');
            $totalKas = $totalSimpananAnggota - $totalPinjamanAktif + $totalKasMasuk - $totalKasKeluar;

            // 3.5 Jumlah Anggota Aktif (dipakai di card ringkasan Beranda Bendahara & Ketua)
            $jumlahAnggotaAktif = User::where('role', 'ANGGOTA')->where('status_keanggotaan', 'AKTIF')->count();

            // 4. Ambil 5 Transaksi Simpanan Terbaru
            $simpananTerbaru = Simpanan::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($s) {
                    return [
                        'jenis' => 'Simpanan ' . ucfirst(strtolower($s->jenis)),
                        'anggota' => $s->user->nama ?? 'Anggota',
                        'nip' => $s->user->nip ?? 'N/A',
                        'kategori' => $s->tipe === 'SETOR' ? 'SETORAN' : 'PENARIKAN',
                        'waktu' => $s->created_at,
                        'jumlah' => (float) $s->jumlah,
                        'status' => 'Berhasil',
                        'tipe' => $s->tipe === 'SETOR' ? 'in' : 'out',
                        'created_at' => $s->created_at,
                    ];
                });

            // 5. Ambil 5 Pengajuan Pinjaman Terbaru
            $pinjamanTerbaru = Pinjaman::with('user')
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($p) {
                    $statusFormatted = $p->status === 'DISETUJUI' ? 'Berhasil' : 'Proses';
                    $tipe = $p->status === 'PENDING' ? 'pending' : 'out';

                    return [
                        'jenis' => 'Pengajuan Pinjaman',
                        'anggota' => $p->user->nama ?? 'Anggota',
                        'nip' => $p->user->nip ?? 'N/A',
                        'kategori' => 'PINJAMAN',
                        'waktu' => $p->created_at,
                        'jumlah' => (float) $p->jumlah,
                        'status' => $statusFormatted,
                        'tipe' => $tipe,
                        'created_at' => $p->created_at,
                    ];
                });

            // Gabungkan dan urutkan 5 aktivitas paling baru secara global
            $aktivitasTerbaru = $simpananTerbaru->concat($pinjamanTerbaru)
                ->sortByDesc('created_at')
                ->take(5)
                ->values();

            return response()->json([
                'status' => 'success',
                'total_kas' => (float) $totalKas,
                'total_simpanan_anggota' => (float) $totalSimpananAnggota,
                'total_pinjaman_aktif' => (float) $totalPinjamanAktif,
                'jumlah_anggota_aktif' => $jumlahAnggotaAktif,
                'sub_saldo' => [
                    'pokok' => (float) $totalPokok,
                    'wajib' => (float) $totalWajib,
                    'sukarela' => (float) $totalSukarela,
                ],
                'aktivitas_terbaru' => $aktivitasTerbaru,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}