<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengurusController extends Controller
{
    // GET /api/ketua/pengurus -> pages/ketua/PengurusAnggota.jsx (tabel Manajemen Pengurus)
    public function index(Request $request)
    {
        $query = User::where('role', 'BENDAHARA');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q->where('nama', 'like', "%{$search}%")->orWhere('nip', 'like', "%{$search}%"));
        }

        return response()->json(
            $query->orderBy('nama')->get(['id', 'nama', 'nip', 'status_keanggotaan', 'created_at'])
        );
    }

    // POST /api/ketua/pengurus -> buat akun Bendahara baru
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'nip' => 'required|string|unique:users,nip',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            ...$validated,
            'role' => 'BENDAHARA',
            'status_keanggotaan' => 'AKTIF',
        ]);

        return response()->json($user, 201);
    }

    // PATCH /api/ketua/pengurus/{pengurus}/status -> nonaktifkan / aktifkan kembali
    public function updateStatus(Request $request, User $pengurus)
    {
        $validated = $request->validate([
            'status_keanggotaan' => ['required', Rule::in(['AKTIF', 'NONAKTIF'])],
        ]);

        $pengurus->update($validated);

        return response()->json(['status_keanggotaan' => $pengurus->status_keanggotaan]);
    }
}
