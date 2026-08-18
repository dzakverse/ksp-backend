<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pinjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    private function tutupPinjamanLamaJikaTopup(Pinjaman $pinjamanBaru, int $userId): void
    {
        if (!$pinjamanBaru->is_topup || !$pinjamanBaru->topup_dari_pinjaman_id) {
            return;
        }

        $pinjamanLama = Pinjaman::find($pinjamanBaru->topup_dari_pinjaman_id);

        if ($pinjamanLama && $pinjamanLama->status !== 'LUNAS') {
            $pinjamanLama->cicilans()->where('status', '!=', 'LUNAS')->update([
                'status' => 'LUNAS',
                'tanggal_bayar' => now()->toDateString(),
                'catatan' => 'Dilunasi otomatis - digabung ke pinjaman Top-Up baru #' . $pinjamanBaru->kode,
                'dibayar_oleh' => $userId,
            ]);
            $pinjamanLama->update(['status' => 'LUNAS']);
        }
    }

    public function index(Request $request)
    {
        $riwayat = $request->user()->pinjamans()->orderByDesc('created_at')->get();

        $aktif = $riwayat->firstWhere('status', 'DISETUJUI');
        $pinjamanAktif = null;

        if ($aktif) {
            $aktif->load('cicilans');
            $totalDibayar = $aktif->cicilans->where('status', 'LUNAS')->sum('jumlah');

            $cicilanPertama = $aktif->cicilans->sortBy('cicilan_ke')->first();
            if ($cicilanPertama) {
                $angsuranPerBulan = $cicilanPertama->jumlah;
            } else {
                $bungaPersen = $aktif->suku_bunga_persen ?? \App\Models\Kebijakan::current()->suku_bunga_persen;
                $pokokBulanan = $aktif->tenor_bulan > 0 ? $aktif->jumlah / $aktif->tenor_bulan : 0;
                $bungaBulanan = $aktif->jumlah * ($bungaPersen / 100);
                $angsuranPerBulan = $pokokBulanan + $bungaBulanan;
            }

            $pinjamanAktif = [
                'kode' => $aktif->kode,
                'jumlah' => $aktif->jumlah,
                'tenor_bulan' => $aktif->tenor_bulan,
                'angsuran_per_bulan' => round($angsuranPerBulan),
                'sisa_pinjaman' => $aktif->jumlah - $totalDibayar,
                'cicilan_lunas' => $aktif->cicilans->where('status', 'LUNAS')->count(),
                'cicilan' => $aktif->cicilans->sortBy('cicilan_ke')->values(),
            ];
        }

        $pengajuanPending = $riwayat->first(fn ($p) => in_array($p->status, ['MENUNGGU', 'DISETUJUI_BENDAHARA']));

        return response()->json([
            'pinjaman_aktif' => $pinjamanAktif,
            'pengajuan_pending' => $pengajuanPending ? [
                'kode' => $pengajuanPending->kode,
                'status' => $pengajuanPending->status,
            ] : null,
            'total_pengajuan' => $riwayat->count(),
            'riwayat' => $riwayat,
        ]);
    }

    public function store(Request $request)
    {
        $kebijakan = \App\Models\Kebijakan::current();
        $plafon = $kebijakan->plafon_maksimal ?? 50000000;

        $validated = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:100000', "max:{$plafon}"],
            'tenor_bulan' => 'required|integer|min:1|max:36',
            'alasan' => 'nullable|string',
            'keterangan' => 'nullable|string',
        ], [
            'jumlah.max' => 'Nominal pengajuan melebihi plafon maksimal yang berlaku (Rp ' . number_format($plafon, 0, ',', '.') . ').',
        ]);

        try {
            DB::beginTransaction();

            \App\Models\User::where('id', $request->user()->id)->lockForUpdate()->first();

            $pengajuanPending = $request->user()->pinjamans()
                ->whereIn('status', ['MENUNGGU', 'DISETUJUI_BENDAHARA'])
                ->first();

            if ($pengajuanPending) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Anda masih punya pengajuan pinjaman #' . $pengajuanPending->kode . ' yang sedang diproses. Tunggu sampai diverifikasi/disetujui/ditolak sebelum mengajukan pinjaman baru.',
                ], 422);
            }

            $alasan = $request->input('alasan') ?? $request->input('keterangan') ?? '-';

            $pinjamanAktif = $request->user()->pinjamans()->where('status', 'DISETUJUI')->first();
            $dataTopup = [];

            if ($pinjamanAktif) {
                $pinjamanAktif->load('cicilans');
                $totalDibayar = $pinjamanAktif->cicilans->where('status', 'LUNAS')->sum('jumlah');
                $sisaPinjamanLama = round($pinjamanAktif->jumlah - $totalDibayar, 2);

                if ($validated['jumlah'] <= $sisaPinjamanLama) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Nominal Top-Up harus lebih besar dari sisa pinjaman lama (Rp ' . number_format($sisaPinjamanLama, 0, ',', '.') . ').',
                    ], 422);
                }

                $tenorLunas = $pinjamanAktif->cicilans->where('status', 'LUNAS')->count();
                $progressPersen = $pinjamanAktif->tenor_bulan > 0
                    ? ($tenorLunas / $pinjamanAktif->tenor_bulan) * 100
                    : 0;
                $minimalProgress = (float) ($kebijakan->minimal_progress_topup_persen ?? 30);

                if ($progressPersen < $minimalProgress) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Pengajuan Top-Up belum memenuhi syarat: minimal {$minimalProgress}% tenor pinjaman lama harus lunas dulu (saat ini baru " . round($progressPersen) . "%).",
                    ], 422);
                }

                $biayaAdmin = $validated['jumlah'] * 0.01;

                $dataTopup = [
                    'is_topup' => true,
                    'topup_dari_pinjaman_id' => $pinjamanAktif->id,
                    'potongan_pelunasan' => $sisaPinjamanLama,
                    'jumlah_pencairan_bersih' => round($validated['jumlah'] - $sisaPinjamanLama - $biayaAdmin, 2),
                ];
            }

            $saldoKas = \App\Models\KasTransaksi::saldoSaatIni();
            if ($validated['jumlah'] > $saldoKas) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Pengajuan tidak bisa diproses karena kas koperasi tidak mencukupi. Saldo kas saat ini hanya Rp ' . number_format($saldoKas, 0, ',', '.') . '.',
                ], 422);
            }

            $pinjaman = $request->user()->pinjamans()->create([
                'kode' => Pinjaman::generateKode(),
                'jumlah' => $validated['jumlah'],
                'tenor_bulan' => $validated['tenor_bulan'],
                'sisa_pokok' => $validated['jumlah'],
                'suku_bunga_persen' => $kebijakan->suku_bunga_persen,
                'alasan' => $alasan,
                'status' => 'MENUNGGU',
                ...$dataTopup,
            ]);

            DB::commit();

            return response()->json($pinjaman, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Gagal memproses pengajuan pinjaman di server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexAll(Request $request)
    {
        $query = Pinjaman::with('user');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->whereHas('user', fn ($q) => $q->where('nama', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"));
        }

        match ($request->query('sort', 'terbaru')) {
            'terlama' => $query->oldest(),
            'nominal' => $query->orderByDesc('jumlah'),
            default => $query->latest(),
        };

        if ($status) {
            $antrean = $query->paginate(10, ['*'], 'antrean_page');

            return response()->json([
                'antrean' => $antrean,
                'antrean_count' => Pinjaman::where('status', $status)->count(),
                'riwayat' => Pinjaman::with('user')
                    ->whereIn('status', ['DISETUJUI', 'DITOLAK'])
                    ->latest()->take(20)->get(),
            ]);
        }

        $antrean = (clone $query)->where('status', 'MENUNGGU')->paginate(10, ['*'], 'antrean_page');
        $riwayat = (clone $query)->whereIn('status', ['DISETUJUI_BENDAHARA', 'DISETUJUI', 'DITOLAK'])
            ->take(20)->get();

        return response()->json([
            'antrean' => $antrean,
            'antrean_count' => Pinjaman::where('status', 'MENUNGGU')->count(),
            'riwayat' => $riwayat,
        ]);
    }

    public function show(Pinjaman $pinjaman)
    {
        $pinjaman->load('user');

        $bungaPersen = $pinjaman->suku_bunga_persen ?? \App\Models\Kebijakan::current()->suku_bunga_persen;
        $biayaAdminRate = 0.01;
        $biayaAdmin = $pinjaman->jumlah * $biayaAdminRate;
        $pokokBulanan = $pinjaman->tenor_bulan > 0 ? $pinjaman->jumlah / $pinjaman->tenor_bulan : 0;
        $bunga = $pinjaman->jumlah * ($bungaPersen / 100);

        $topupPreview = null;

        if ($pinjaman->is_topup && $pinjaman->topup_dari_pinjaman_id) {
            $pinjamanLama = $pinjaman->topupDariPinjaman;

            if ($pinjamanLama) {
                $pinjamanLama->load('cicilans');
                $tenorLunas = $pinjamanLama->cicilans->where('status', 'LUNAS')->count();
                $progressPersen = $pinjamanLama->tenor_bulan > 0
                    ? round(($tenorLunas / $pinjamanLama->tenor_bulan) * 100)
                    : 0;

                $topupPreview = [
                    'pinjaman_lama_kode' => $pinjamanLama->kode,
                    'progress_persen' => $progressPersen,
                    'pencairan_bersih' => (float) $pinjaman->jumlah_pencairan_bersih,
                ];
            }
        }

        return response()->json([
            'pinjaman' => $pinjaman,
            'simulasi' => [
                'pokok_bulanan' => round($pokokBulanan),
                'bunga' => round($bunga),
                'bunga_persen' => (float) $bungaPersen,
                'biaya_admin' => round($biayaAdmin),
                'biaya_admin_persen' => $biayaAdminRate * 100,
                'uang_diterima' => round($pinjaman->jumlah - $biayaAdmin),
                'total_per_bulan' => round($pokokBulanan + $bunga),
            ],
            'topup_preview' => $topupPreview,
        ]);
    }

    public function verifikasi(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

        if ($pinjaman->status !== 'MENUNGGU') {
            return response()->json([
                'message' => 'Pengajuan ini sudah diproses sebelumnya (status saat ini: ' . $pinjaman->status . '). Tidak bisa diverifikasi ulang.',
            ], 422);
        }

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

    private function pastikanTidakNumpukPinjamanAktif(Pinjaman $pinjaman): ?string
    {
        $query = Pinjaman::where('user_id', $pinjaman->user_id)
            ->where('status', 'DISETUJUI')
            ->where('id', '!=', $pinjaman->id);

        if ($pinjaman->is_topup && $pinjaman->topup_dari_pinjaman_id) {
            $query->where('id', '!=', $pinjaman->topup_dari_pinjaman_id);
        }

        $bentrok = $query->first();

        if ($bentrok) {
            return 'Anggota ini sudah punya pinjaman aktif lain #' . $bentrok->kode . ' yang belum lunas/tergabung. Tidak bisa ACC pengajuan ini sampai itu diselesaikan (lunasi, atau ajukan ulang sebagai Top-Up).';
        }

        return null;
    }

    // POST /api/ketua/pinjaman/{id}/persetujuan -> Ketua approve final
    public function persetujuanKetua(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:DISETUJUI,DITOLAK',
            'catatan_verifikasi' => 'nullable|string',
        ]);

        if ($validated['status'] === 'DISETUJUI') {
            $pesanBentrok = $this->pastikanTidakNumpukPinjamanAktif($pinjaman);
            if ($pesanBentrok) {
                return response()->json(['message' => $pesanBentrok], 422);
            }
        }

        DB::transaction(function () use ($request, $pinjaman, $validated) {
            $pinjaman->update([
                ...$validated,
                'diverifikasi_oleh' => $request->user()->id,
            ]);

            if ($validated['status'] === 'DISETUJUI') {
                $pinjaman->generateCicilanSchedule();
                $this->tutupPinjamanLamaJikaTopup($pinjaman, $request->user()->id);
            }
        });

        return response()->json($pinjaman->fresh());
    }

    // GET /api/ketua/pinjaman/bypass-queue
    public function bypassQueue()
    {
        $antrean = Pinjaman::with('user')->where('status', 'MENUNGGU')->oldest()->get();

        return response()->json([
            'antrean' => $antrean,
            'total_menunggu' => $antrean->count(),
        ]);
    }

    // POST /api/ketua/pinjaman/{id}/bypass
    public function bypass(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

        $validated = $request->validate([
            'catatan_verifikasi' => 'nullable|string',
        ]);

        $pesanBentrok = $this->pastikanTidakNumpukPinjamanAktif($pinjaman);
        if ($pesanBentrok) {
            return response()->json(['message' => $pesanBentrok], 422);
        }

        DB::transaction(function () use ($request, $pinjaman, $validated) {
            $pinjaman->update([
                'status' => 'DISETUJUI',
                'is_bypassed' => true,
                'bypassed_by' => $request->user()->id,
                'diverifikasi_oleh' => $request->user()->id,
                'catatan_verifikasi' => $validated['catatan_verifikasi'] ?? 'Disetujui via Emergency Bypass Ketua',
                'suku_bunga_persen' => 0,
            ]);

            $pinjaman->generateCicilanSchedule();

            $this->tutupPinjamanLamaJikaTopup($pinjaman, $request->user()->id);
        });

        return response()->json($pinjaman->fresh());
    }

    // POST /api/ketua/pinjaman/{id}/restrukturisasi
    public function restrukturisasi(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

        $validated = $request->validate([
            'jumlah_tambahan' => 'required|numeric|min:1',
            'tenor_bulan' => 'required|integer|min:1|max:36',
            'catatan' => 'nullable|string',
        ]);

        if ($pinjaman->status !== 'DISETUJUI') {
            return response()->json(['message' => 'Hanya pinjaman yang sedang aktif (DISETUJUI) yang bisa direstrukturisasi.'], 422);
        }

        if ($pinjaman->pinjamanBaru()->exists()) {
            return response()->json(['message' => 'Pinjaman ini sudah pernah direstrukturisasi sebelumnya.'], 422);
        }

        $pinjaman->load('cicilans');
        $sisaCicilan = $pinjaman->cicilans->where('status', 'BELUM_BAYAR')->count();
        $pokokBulanan = $pinjaman->tenor_bulan > 0 ? $pinjaman->jumlah / $pinjaman->tenor_bulan : 0;
        $sisaPokokLama = round($pokokBulanan * $sisaCicilan, 2);

        if ($sisaPokokLama <= 0) {
            return response()->json(['message' => 'Pinjaman ini sudah lunas — anggota bisa langsung mengajukan pinjaman baru biasa tanpa restrukturisasi.'], 422);
        }

        $jumlahTambahan = (float) $validated['jumlah_tambahan'];
        $jumlahBaru = $sisaPokokLama + $jumlahTambahan;

        $saldoKas = \App\Models\KasTransaksi::saldoSaatIni();
        if ($jumlahTambahan > $saldoKas) {
            return response()->json([
                'message' => 'Restrukturisasi tidak bisa diproses karena kas koperasi tidak mencukupi untuk mencairkan tambahan Rp ' . number_format($jumlahTambahan, 0, ',', '.') . '. Saldo kas saat ini hanya Rp ' . number_format($saldoKas, 0, ',', '.') . '.',
            ], 422);
        }

        $ringkasan = 'Pinjaman Lama (sisa Rp ' . number_format($sisaPokokLama, 0, ',', '.')
            . ') + Pinjaman Baru (Rp ' . number_format($jumlahTambahan, 0, ',', '.')
            . ') = Total Hutang Baru Rp ' . number_format($jumlahBaru, 0, ',', '.');

        $baru = DB::transaction(function () use ($request, $pinjaman, $validated, $sisaPokokLama, $jumlahBaru, $ringkasan) {
            $pinjaman->cicilans()->where('status', 'BELUM_BAYAR')->update([
                'status' => 'LUNAS',
                'tanggal_bayar' => now()->toDateString(),
                'catatan' => 'Dilunasi otomatis - digabung ke pinjaman baru hasil restrukturisasi/top-up',
                'dibayar_oleh' => $request->user()->id,
            ]);
            $pinjaman->update(['status' => 'LUNAS']);

            return Pinjaman::create([
                'kode' => Pinjaman::generateKode(),
                'user_id' => $pinjaman->user_id,
                'pinjaman_lama_id' => $pinjaman->id,
                'jumlah' => $jumlahBaru,
                'sisa_pokok' => $jumlahBaru,
                'tenor_bulan' => $validated['tenor_bulan'],
                'suku_bunga_persen' => $pinjaman->suku_bunga_persen,
                'status' => 'DISETUJUI',
                'is_restrukturisasi' => true,
                'sisa_pokok_lama' => $sisaPokokLama,
                'alasan' => 'Restrukturisasi/Top-up dari pinjaman #' . $pinjaman->kode,
                'catatan_verifikasi' => $validated['catatan'] ? $validated['catatan'] . ' | ' . $ringkasan : $ringkasan,
                'diverifikasi_oleh' => $request->user()->id,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json($baru->fresh('cicilans'), 201);
    }

    // POST /api/admin/cicilan/{cicilan}/bayar
    public function bayarCicilan(Request $request, \App\Models\Cicilan $cicilan)
    {
        $validated = $request->validate([
            'catatan' => 'required|string|max:500',
        ]);

        if ($cicilan->status === 'LUNAS') {
            return response()->json(['message' => 'Cicilan ini sudah tercatat lunas.'], 422);
        }

        $cicilan->update([
            'status' => 'LUNAS',
            'tanggal_bayar' => now()->toDateString(),
            'catatan' => $validated['catatan'],
            'dibayar_oleh' => $request->user()->id,
        ]);

        $pinjaman = $cicilan->pinjaman;
        $masihAdaBelumLunas = $pinjaman->cicilans()->where('status', '!=', 'LUNAS')->exists();
        if (!$masihAdaBelumLunas) {
            $pinjaman->update(['status' => 'LUNAS']);
        }

        return response()->json([
            'cicilan' => $cicilan->fresh(),
            'pinjaman_status' => $pinjaman->fresh()->status,
        ]);
    }
}