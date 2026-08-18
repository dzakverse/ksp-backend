<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pinjaman;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PinjamanController extends Controller
{
    // Dipakai bareng oleh persetujuanKetua() dan bypass() supaya pinjaman lama
    // SELALU otomatis dilunasi begitu pengajuan Top-Up-nya disetujui, lewat
    // jalur mana pun (antrean normal ATAU emergency bypass). Sebelumnya logic
    // ini cuma ada di persetujuanKetua(), jadi kalau pengajuan Top-Up di-ACC
    // lewat Emergency Bypass, pinjaman lama nyangkut selamanya di status
    // DISETUJUI (Aktif/Berjalan) walau anggota sudah menganggapnya "digabung".
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

            // Lock baris user ini selama transaksi berjalan. Tujuannya BUKAN buat
            // proteksi data user, tapi supaya kalau ada 2 request submit yang
            // nyaris bersamaan (double-klik, atau dipanggil manual berkali-kali
            // ke API), request kedua WAJIB nunggu request pertama commit/rollback
            // dulu sebelum lanjut cek "masih ada pengajuan pending?" di bawah.
            // Tanpa lock ini, dua transaksi bisa sama-sama lolos pengecekan
            // "belum ada yang pending" secara bersamaan (race condition) sebelum
            // salah satunya sempat commit ke DB.
            \App\Models\User::where('id', $request->user()->id)->lockForUpdate()->first();

            // ---- Guard anti-spam: anggota tidak boleh punya lebih dari satu
            // pengajuan yang masih berjalan di antrean (MENUNGGU / sudah
            // diverifikasi Bendahara tapi belum di-ACC Ketua). Tanpa ini,
            // anggota bisa submit berkali-kali sebelum ada yang diproses, lalu
            // kalau semuanya di-ACC satu-satu oleh Bendahara+Ketua, tiap
            // pengajuan dianggap independen (bukan Top-Up, karena saat masing-
            // masing disubmit belum ada pinjaman berstatus DISETUJUI) -> hasil
            // akhirnya anggota bisa punya banyak pinjaman DISETUJUI aktif
            // sekaligus, padahal seharusnya cuma boleh satu.
            $pengajuanPending = $request->user()->pinjamans()
                ->whereIn('status', ['MENUNGGU', 'DISETUJUI_BENDAHARA'])
                ->first();

            if ($pengajuanPending) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Anda masih punya pengajuan pinjaman #' . $pengajuanPending->kode . ' yang sedang diproses. Tunggu sampai diverifikasi/disetujui/ditolak sebelum mengajukan pinjaman baru.',
                ], 422);
            }

            // Mencegah crash jika input bernama 'keterangan' alih-alih 'alasan'
            $alasan = $request->input('alasan') ?? $request->input('keterangan') ?? '-';

            // ---- Deteksi mode Top-Up: anggota masih punya pinjaman aktif? ----
            // Ini yang tadinya CUMA dicek di frontend (ajukan.jsx) buat nampilin
            // banner "Top-Up", tapi datanya tidak pernah dikirim/disimpan ke
            // backend -> makanya pinjaman lama tidak pernah otomatis ke-lunas
            // begitu top-up-nya di-ACC Ketua. Sekarang dicek & disimpan di sini.
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

            // ---- Guard kas koperasi: pengajuan tidak boleh melebihi saldo kas
            // yang benar-benar tersedia sekarang. Tanpa ini, anggota tetap bisa
            // mengajukan (dan Bendahara/Ketua bisa tetap meng-ACC) walau uang
            // fisiknya sebenarnya tidak cukup di kas koperasi.
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
                'sisa_pokok' => $validated['jumlah'], // Wajib diisi agar database tidak error
                // Snapshot suku bunga yang berlaku SEKARANG ke pinjaman ini secara
                // permanen -> tanpa ini nilainya nyangkut di default kolom (0), dan
                // "Progres Cicilan" (Anggota) maupun simulasi verifikasi (Bendahara)
                // sama-sama menghitung bunga jadi Rp 0 walau Kebijakan sudah di-set.
                'suku_bunga_persen' => $kebijakan->suku_bunga_persen,
                'alasan' => $alasan,
                'status' => 'MENUNGGU',
                ...$dataTopup,
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

        // Breakdown Top-Up untuk halaman Verifikasi Bendahara (VerifikasiDetail.jsx)
        // & modal ACC Top-Up Ketua (PersetujuanPinjaman.jsx). Sebelumnya field ini
        // TIDAK PERNAH dikirim dari sini walau kedua halaman itu sudah minta
        // `topup_preview` -> card breakdown-nya selalu kosong/hilang di FE.
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
                    // Dipakai nilai yang di-snapshot saat pengajuan (potongan_pelunasan /
                    // jumlah_pencairan_bersih) supaya konsisten dengan angka yang sudah
                    // ditampilkan ke anggota saat submit di ajukan.jsx, bukan dihitung
                    // ulang dari sisa cicilan sekarang (bisa beda kalau ada cicilan yang
                    // baru dibayar setelah pengajuan Top-Up ini dibuat).
                    'sisa_pokok_saat_ini' => (float) $pinjaman->potongan_pelunasan,
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

    // Lapis kedua (defense in depth) selain guard di store(): pastikan anggota
    // tidak akan punya 2+ pinjaman berstatus DISETUJUI secara bersamaan saat
    // di-ACC. Guard di store() harusnya sudah cegah dari akarnya, tapi ini
    // jaga-jaga untuk data lama yang kadung numpuk atau jalur lain yang lolos.
    private function pastikanTidakNumpukPinjamanAktif(Pinjaman $pinjaman): ?string
    {
        $query = Pinjaman::where('user_id', $pinjaman->user_id)
            ->where('status', 'DISETUJUI')
            ->where('id', '!=', $pinjaman->id);

        // Kalau ini pengajuan Top-Up, pinjaman lama yang jadi sumbernya WAJAR
        // masih berstatus DISETUJUI di titik ini (baru akan ditutup otomatis
        // setelah blok ini) -> jangan dianggap "numpuk".
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
                // Emergency Bypass = kebijakan kemanusiaan tanpa bunga. Frontend
                // (EmergencyBypass.jsx -> getSimulasi()) SUDAH menampilkan simulasi
                // 0% bunga ke Ketua sebelum dieksekusi, tapi sebelumnya field ini
                // tidak pernah di-override di sini -> generateCicilanSchedule()
                // otomatis terpanggil lewat hook saved() di atas dengan bunga NORMAL
                // dari Kebijakan, jadi anggota tetap kena bunga penuh walau sudah
                // dijanjikan 0% di layar Ketua. Di-set 0 di sini supaya jadwal
                // cicilan yang ter-generate match persis dengan apa yang disetujui.
                'suku_bunga_persen' => 0,
            ]);

            $pinjaman->generateCicilanSchedule();

            // Bypass Queue tetap bisa dipakai buat pengajuan Top-Up (statusnya
            // sama-sama MENUNGGU di antrean) -> pinjaman lama harus tetap
            // otomatis dilunasi sama seperti jalur ACC normal, pakai helper
            // yang sama dengan persetujuanKetua() supaya tidak ada lagi celah.
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

        // Sertakan status pinjaman TERBARU di response -> tanpa ini, frontend cuma
        // dapat data cicilan-nya saja dan tidak tahu status induk pinjaman ikut
        // berubah jadi LUNAS, jadi card di halaman Data Anggota tetap kebaca
        // "Aktif/Berjalan" sampai halaman di-refresh manual.
        return response()->json([
            'cicilan' => $cicilan->fresh(),
            'pinjaman_status' => $pinjaman->fresh()->status,
        ]);
    }
}