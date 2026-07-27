<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login  -> ganti logika usersData.find() di FE dengan ini
    public function login(Request $request)
    {
        $validated = $request->validate([
            'nip' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('nip', $validated['nip'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'nip' => ['NIP atau kata sandi salah.'],
            ]);
        }

        // Buat token asli (bukan dummy-token lagi)
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'nama' => $user->nama,
                'role' => $user->role,
                'id_anggota' => $user->id_anggota,
            ],
        ]);
    }

    // GET /api/me -> untuk validasi token & ambil ulang data user saat refresh
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    // POST /api/logout
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
