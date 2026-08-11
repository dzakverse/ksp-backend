<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pinjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    // GET /api/pinjaman -> punya user yang login sendiri (pages/pinjaman.jsx)
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

        return response()->json([
            'pinjaman_aktif' => $pinjamanAktif,
            'total_pengajuan' => $riwayat->count(),
            'riwayat' => $riwayat,
        ]);
    }

    // POST /api/pinjaman -> pengajuan baru (pages/ajukan.jsx)
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

            // Mencegah crash jika input bernama 'keterangan' alih-alih 'alasan'
            $alasan = $request->input('alasan') ?? $request->input('keterangan') ?? '-';

            $pinjaman = $request->user()->pinjamans()->create([
                'kode' => 'LN-' . now()->format('Y') . '-' . str_pad(Pinjaman::count() + 1, 3, '0', STR_PAD_LEFT),
                'jumlah' => $validated['jumlah'],
                'tenor_bulan' => $validated['tenor_bulan'],
                'sisa_pokok' => $validated['jumlah'], // Wajib diisi agar database tidak error
                'alasan' => $alasan,
                'status' => 'MENUNGGU',
            ]);

            DB::commit();

            return response()->json($pinjaman, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            // Mengembalikan pesan error log yang jelas ke frontend
            return response()->json([
                'message' => 'Gagal memproses pengajuan pinjaman di server.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // GET /api/admin/pinjaman -> dipakai Bendahara & Ketua
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

    // GET /api/admin/pinjaman/{pinjaman}
    public function show(Pinjaman $pinjaman)
    {
        $pinjaman->load('user');

        $bungaPersen = $pinjaman->suku_bunga_persen ?? \App\Models\Kebijakan::current()->suku_bunga_persen;
        $biayaAdminRate = 0.01;
        $biayaAdmin = $pinjaman->jumlah * $biayaAdminRate;
        $pokokBulanan = $pinjaman->tenor_bulan > 0 ? $pinjaman->jumlah / $pinjaman->tenor_bulan : 0;
        $bunga = $pinjaman->jumlah * ($bungaPersen / 100);

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
        ]);
    }

    // POST /api/admin/pinjaman/{id}/verifikasi -> Bendahara verifikasi
    public function verifikasi(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

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

    // POST /api/ketua/pinjaman/{id}/persetujuan -> Ketua approve final
    public function persetujuanKetua(Request $request, $id)
    {
        $pinjaman = Pinjaman::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:DISETUJUI,DITOLAK',
            'catatan_verifikasi' => 'nullable|string',
        ]);

        $pinjaman->update([
            ...$validated,
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        if ($validated['status'] === 'DISETUJUI') {
            $pinjaman->generateCicilanSchedule();
        }

        return response()->json($pinjaman);
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

        $pinjaman->update([
            'status' => 'DISETUJUI',
            'is_bypassed' => true,
            'bypassed_by' => $request->user()->id,
            'diverifikasi_oleh' => $request->user()->id,
            'catatan_verifikasi' => $validated['catatan_verifikasi'] ?? 'Disetujui via Emergency Bypass Ketua',
        ]);

        $pinjaman->generateCicilanSchedule();

        return response()->json($pinjaman);
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
                'kode' => 'LN-' . now()->format('Y') . '-' . str_pad(Pinjaman::count() + 1, 3, '0', STR_PAD_LEFT),
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

        return response()->json($cicilan->fresh());
    }
}