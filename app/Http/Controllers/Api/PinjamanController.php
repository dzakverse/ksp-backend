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

        $aktif = $riwayat->firstWhere('status', 'DISETUJUI');
        $pinjamanAktif = null;

        if ($aktif) {
            $aktif->load('cicilans');
            $totalDibayar = $aktif->cicilans->where('status', 'LUNAS')->sum('jumlah');

            // Ambil nominal angsuran dari jadwal cicilan yang sudah di-generate
            // (generateCicilanSchedule() sudah menghitung pokok + bunga dengan benar).
            // Fallback ke pokok+bunga manual hanya kalau cicilan belum ter-generate
            // sama sekali (harusnya tidak terjadi untuk pinjaman berstatus DISETUJUI).
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
        $plafon = \App\Models\Kebijakan::current()->plafon_maksimal;

        $validated = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:100000', "max:{$plafon}"],
            'tenor_bulan' => 'required|integer|min:1|max:36',
            'alasan' => 'nullable|string',
        ], [
            'jumlah.max' => 'Nominal pengajuan melebihi plafon maksimal yang berlaku (Rp ' . number_format($plafon, 0, ',', '.') . ').',
        ]);

        $pinjaman = $request->user()->pinjamans()->create([
            ...$validated,
            'kode' => 'LN-' . now()->format('Y') . '-' . str_pad(Pinjaman::count() + 1, 3, '0', STR_PAD_LEFT),
            'status' => 'MENUNGGU',
        ]);

        return response()->json($pinjaman, 201);
    }

    // GET /api/admin/pinjaman -> dipakai Bendahara (VerifikasiPinjaman.jsx) & Ketua (PersetujuanPinjaman.jsx)
    // Query params: status (filter tunggal, mis. MENUNGGU / DISETUJUI_BENDAHARA), search, sort, page
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

        // Jika status difilter -> kembalikan sebagai antrean (dipaginate).
        // Jika tidak -> kembalikan snapshot antrean MENUNGGU + riwayat gabungan (dipakai Bendahara).
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

    // GET /api/admin/pinjaman/{pinjaman} -> pages/admin/VerifikasiDetail.jsx
    public function show(Pinjaman $pinjaman)
    {
        $pinjaman->load('user');

        // Simulasi cicilan. Disamakan dengan rumus yang dipakai anggota saat
        // pengajuan (pages/ajukan.jsx) dan generateCicilanSchedule() di model
        // Pinjaman: bunga bulanan dinamis dari Kebijakan (bisa diubah Ketua),
        // biaya admin 1% dari nominal (dipotong sekali dari saldo cair, BUKAN
        // komponen cicilan bulanan).
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

        $pinjaman->update([
            ...$validated,
            'diverifikasi_oleh' => $request->user()->id,
        ]);

        if ($validated['status'] === 'DISETUJUI') {
            $pinjaman->generateCicilanSchedule();
        }

        return response()->json($pinjaman);
    }

    // GET /api/ketua/pinjaman/bypass-queue -> pages/ketua/EmergencyBypass.jsx
    // Ambil SELURUH pengajuan berstatus MENUNGGU, diurutkan dari yang paling lama
    // mengajukan (paling mendesak) ke yang paling baru.
    public function bypassQueue()
    {
        $antrean = Pinjaman::with('user')->where('status', 'MENUNGGU')->oldest()->get();

        return response()->json([
            'antrean' => $antrean,
            'total_menunggu' => $antrean->count(),
        ]);
    }

    // POST /api/ketua/pinjaman/{id}/bypass -> eksekusi Emergency Bypass (skip semua tahap verifikasi)
    public function bypass(Request $request, Pinjaman $pinjaman)
    {
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

    // POST /api/ketua/pinjaman/{pinjaman}/restrukturisasi -> Ketua menggabungkan sisa
    // pinjaman lama yang masih aktif dengan pengajuan pinjaman baru (top-up).
    // Contoh: Pinjaman Lama (sisa 7jt) + Pinjaman Baru (3jt) = Total Hutang Baru 10jt.
    // Pinjaman lama otomatis ditutup (LUNAS) dan seluruh cicilan sisa dianggap
    // terbayar lewat penggabungan; pinjaman baru langsung DISETUJUI dengan jadwal
    // cicilan baru dari total gabungan.
    public function restrukturisasi(Request $request, Pinjaman $pinjaman)
    {
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

        $baru = \Illuminate\Support\Facades\DB::transaction(function () use ($request, $pinjaman, $validated, $sisaPokokLama, $jumlahBaru, $ringkasan) {
            // Tutup pinjaman lama: seluruh cicilan sisa dianggap lunas lewat penggabungan
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
            // generateCicilanSchedule() otomatis terpanggil lewat model event Pinjaman::booted()
            // karena status baru ini langsung DISETUJUI.
        });

        return response()->json($baru->fresh('cicilans'), 201);
    }

    // POST /api/admin/cicilan/{cicilan}/bayar -> Bendahara konfirmasi pembayaran 1 angsuran
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

        return response()->json($cicilan->fresh());
    }
}