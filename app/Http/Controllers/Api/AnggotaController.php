<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Simpanan;
use Illuminate\Http\Request;

class AnggotaController extends Controller
{
    public function index(Request $request)
    {
        try {
            $search = $request->input('search');
            $perPage = $request->input('per_page', 10);

            // Filter khusus role anggota
            $query = User::where('role', 'ANGGOTA');

            // Pencarian nama, nip, atau email
            $query->when($search, function ($q) use ($search) {
                return $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('no_anggota', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });

            $paginated = $query->latest()->paginate($perPage);

            // Mapping data agar menyajikan struktur `saldo` lengkap untuk React
            $items = collect($paginated->items())->map(function ($user) {
                // Kalkulasi saldo simpanan per user
                $pokok = Simpanan::where('user_id', $user->id)->where('jenis', 'POKOK')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;
                $wajib = Simpanan::where('user_id', $user->id)->where('jenis', 'WAJIB')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;

                $sukarelaSetor = Simpanan::where('user_id', $user->id)->where('jenis', 'SUKARELA')->where('tipe', 'SETOR')->sum('jumlah') ?? 0;
                $sukarelaTarik = Simpanan::where('user_id', $user->id)->where('jenis', 'SUKARELA')->where('tipe', 'TARIK')->sum('jumlah') ?? 0;
                $sukarela = $sukarelaSetor - $sukarelaTarik;

                return [
                    'id' => $user->id,
                    'nama' => $user->name,
                    'nip' => $user->no_anggota ?? '-',
                    'unit_kerja' => $user->unit_kerja ?? 'Dinas Sosial',
                    'saldo' => [
                        'pokok' => (float) $pokok,
                        'wajib' => (float) $wajib,
                        'sukarela' => (float) $sukarela,
                        'total' => (float) ($pokok + $wajib + $sukarela),
                    ]
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
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data dari server.',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }
}