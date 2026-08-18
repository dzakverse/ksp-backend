<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Simpanan;
use Illuminate\Http\Request;

class SimpananController extends Controller
{
    // GET /api/simpanan -> data buat dashboard.jsx & simpananku.jsx
    public function index(Request $request)
    {
        $user = $request->user();

        $simpanans = $user->simpanans()->orderByDesc('tanggal')->get();
        $saldo = Simpanan::breakdownSaldo($user->id);

        return response()->json([
            'total_pokok' => $saldo['pokok'],
            'total_wajib' => $saldo['wajib'],
            'total_sukarela' => $saldo['sukarela'],
            'total_keseluruhan' => $saldo['total'],
            'riwayat' => $simpanans,
        ]);
    }

    // POST /api/simpanan/tarik -> pages/simpananku.jsx (request tarik Simpanan Sukarela mandiri)
    // Hanya Sukarela yang boleh ditarik mandiri oleh Anggota (Pokok & Wajib tidak
    // bisa ditarik selama masih jadi anggota). Request masuk sebagai PENDING,
    // baru benar-benar memotong saldo setelah dieksekusi/dikonfirmasi Bendahara.
    public function requestTarik(Request $request)
    {
        $validated = $request->validate([
            'jumlah' => 'required|numeric|min:1',
            'keterangan' => 'nullable|string',
        ]);

        $user = $request->user();

        $totalSetor = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $totalTarik = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
        $totalPending = $user->simpanans()->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->where('status', 'PENDING')->sum('jumlah');
        $saldoTersedia = $totalSetor - $totalTarik - $totalPending;

        if ($validated['jumlah'] > $saldoTersedia) {
            return response()->json([
                'message' => 'Saldo Simpanan Sukarela tidak mencukupi untuk penarikan sejumlah itu.',
                'saldo_tersedia' => $saldoTersedia,
            ], 422);
        }

        $simpanan = $user->simpanans()->create([
            'jenis' => 'SUKARELA',
            'tipe' => 'TARIK',
            'jumlah' => $validated['jumlah'],
            'keterangan' => $validated['keterangan'] ?? 'Request tarik mandiri oleh anggota',
            'status' => 'PENDING',
            'tanggal' => now()->toDateString(),
            'created_by' => $user->id,
        ]);

        return response()->json($simpanan, 201);
    }

    // GET /api/admin/simpanan/pending -> daftar request tarik dari Anggota yang menunggu konfirmasi Bendahara
    public function pendingRequests()
    {
        return response()->json(
            Simpanan::with('user:id,nama,nip')
                ->where('tipe', 'TARIK')
                ->where('status', 'PENDING')
                ->latest()
                ->get()
        );
    }

    // POST /api/admin/simpanan/{simpanan}/konfirmasi -> Bendahara eksekusi (approve) atau tolak request tarik Anggota
    public function konfirmasiTarik(Request $request, Simpanan $simpanan)
    {
        $validated = $request->validate([
            'status' => 'required|in:BERHASIL,GAGAL',
            'catatan' => 'nullable|string',
        ]);

        if ($simpanan->status !== 'PENDING') {
            return response()->json(['message' => 'Transaksi ini sudah pernah diproses.'], 422);
        }

        $simpanan->update([
            'status' => $validated['status'],
            'diproses_oleh' => $request->user()->id,
            'keterangan' => $validated['catatan']
                ? $simpanan->keterangan . ' | Catatan Bendahara: ' . $validated['catatan']
                : $simpanan->keterangan,
        ]);

        return response()->json($simpanan->fresh());
    }
}
