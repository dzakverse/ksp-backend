<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simpanan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnggotaController extends Controller
{
    // GET /api/admin/anggota -> pages/admin/DataAnggota.jsx
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = (int) $request->input('per_page', 10);

        $query = User::where('role', 'ANGGOTA');

        $query->when($search, function ($q) use ($search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('nama', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });

        $paginated = $query->orderBy('nama')->paginate($perPage);

        // Mapping data agar menyajikan struktur `saldo` lengkap untuk React
        $items = collect($paginated->items())->map(function (User $user) {
            $pokok = Simpanan::where('user_id', $user->id)->where('jenis', 'POKOK')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
            $wajib = Simpanan::where('user_id', $user->id)->where('jenis', 'WAJIB')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');

            $sukarelaSetor = Simpanan::where('user_id', $user->id)->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
            $sukarelaTarik = Simpanan::where('user_id', $user->id)->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
            $sukarela = $sukarelaSetor - $sukarelaTarik;

            return [
                'id' => $user->id,
                'nama' => $user->nama,
                'nip' => $user->nip,
                'unit_kerja' => $user->unit_kerja ?? '-',
                'status_keanggotaan' => $user->status_keanggotaan,
                'saldo' => [
                    'pokok' => (float) $pokok,
                    'wajib' => (float) $wajib,
                    'sukarela' => (float) $sukarela,
                    'total' => (float) ($pokok + $wajib + $sukarela),
                ],
            ];
        });

        return response()->json([
            'data' => $items,
            'total' => $paginated->total(),
            'from' => $paginated->firstItem() ?? 0,
            'to' => $paginated->lastItem() ?? 0,
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    // GET /api/admin/anggota/{anggota} -> pages/admin/DetailAnggota.jsx
    public function show(User $anggota)
    {
        $pokok = $anggota->simpanans()->where('jenis', 'POKOK')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $wajib = $anggota->simpanans()->where('jenis', 'WAJIB')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $sukarelaSetor = $anggota->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $sukarelaTarik = $anggota->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');

        // Pinjaman aktif (jika ada) - agar Bendahara/Ketua bisa lihat sekilas saat membuka detail anggota
        $pinjamanAktif = $anggota->pinjamans()->where('status', 'DISETUJUI')->latest()->first();

        return response()->json([
            'id' => $anggota->id,
            'nama' => $anggota->nama,
            'nip' => $anggota->nip,
            'unit_kerja' => $anggota->unit_kerja ?? '-',
            'status_keanggotaan' => $anggota->status_keanggotaan,
            'saldo' => [
                'pokok' => (float) $pokok,
                'wajib' => (float) $wajib,
                'sukarela' => (float) ($sukarelaSetor - $sukarelaTarik),
                'total' => (float) ($pokok + $wajib + ($sukarelaSetor - $sukarelaTarik)),
            ],
            'pinjaman_aktif' => $pinjamanAktif ? [
                'kode' => $pinjamanAktif->kode,
                'jumlah' => (float) $pinjamanAktif->jumlah,
                'tenor_bulan' => $pinjamanAktif->tenor_bulan,
            ] : null,
            'daftar_pinjaman' => $anggota->pinjamans()
                ->with(['cicilans', 'pinjamanLama:id,kode'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'kode' => $p->kode,
                    'jumlah' => (float) $p->jumlah,
                    'tenor_bulan' => $p->tenor_bulan,
                    'status' => $p->status,
                    'alasan' => $p->alasan,
                    'created_at' => $p->created_at,
                    'is_restrukturisasi' => (bool) $p->is_restrukturisasi,
                    'pinjaman_lama_kode' => $p->pinjamanLama?->kode,
                    'cicilan' => $p->cicilans->sortBy('cicilan_ke')->values(),
                ]),
            'riwayat_simpanan' => $anggota->simpanans()->orderByDesc('tanggal')->take(20)->get(),
        ]);
    }

    // POST /api/admin/anggota/{anggota}/simpanan -> form "Tambah Simpanan Manual"
    public function storeSimpanan(Request $request, User $anggota)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:POKOK,WAJIB,SUKARELA',
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'nullable|string',
        ]);

        $simpanan = $anggota->simpanans()->create([
            ...$validated,
            'tipe' => 'SETOR',
            'status' => 'BERHASIL',
            'tanggal' => now()->toDateString(),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($simpanan, 201);
    }

    // PATCH /api/admin/anggota/{anggota}/status -> toggle switch aktif/nonaktif
    public function updateStatus(Request $request, User $anggota)
    {
        $validated = $request->validate([
            'status_keanggotaan' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $anggota->update($validated);

        return response()->json(['status_keanggotaan' => $anggota->status_keanggotaan]);
    }

    // POST /api/admin/anggota/{anggota}/simpanan/tarik -> Bendahara/Ketua tarik simpanan langsung
    // (semua jenis: POKOK, WAJIB, SUKARELA - dengan pengecekan saldo cukup)
    public function tarikSimpanan(Request $request, User $anggota)
    {
        $validated = $request->validate([
            'jenis' => 'required|in:POKOK,WAJIB,SUKARELA',
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'nullable|string',
        ]);

        $totalSetor = $anggota->simpanans()->where('jenis', $validated['jenis'])->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $totalTarik = $anggota->simpanans()->where('jenis', $validated['jenis'])->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
        $totalPending = $anggota->simpanans()->where('jenis', $validated['jenis'])->where('tipe', 'TARIK')->where('status', 'PENDING')->sum('jumlah');
        $saldoTersedia = $totalSetor - $totalTarik - $totalPending;

        if ($validated['jumlah'] > $saldoTersedia) {
            return response()->json([
                'message' => "Saldo Simpanan {$validated['jenis']} anggota ini tidak mencukupi.",
                'saldo_tersedia' => $saldoTersedia,
            ], 422);
        }

        $simpanan = $anggota->simpanans()->create([
            'jenis' => $validated['jenis'],
            'tipe' => 'TARIK',
            'jumlah' => $validated['jumlah'],
            'keterangan' => $validated['keterangan'] ?? 'Penarikan langsung oleh pengurus',
            'status' => 'BERHASIL',
            'tanggal' => now()->toDateString(),
            'created_by' => $request->user()->id,
            'diproses_oleh' => $request->user()->id,
        ]);

        return response()->json($simpanan, 201);
    }
}
