<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/login
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

        // Cegah login jika akun sudah dinonaktifkan (mis. oleh Bendahara/Ketua/Super Admin)
        if ($user->status_keanggotaan === 'NONAKTIF') {
            throw ValidationException::withMessages([
                'nip' => ['Akun ini sudah dinonaktifkan. Hubungi pengurus koperasi.'],
            ]);
        }

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

    // GET /api/me
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

    // POST /api/change-password -> pages/admin/UbahPassword.jsx, ketua/UbahPasswordKetua.jsx
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'password_lama' => 'required|string',
            'password_baru' => 'required|string|min:8',
        ]);

        $user = $request->user();

        if (! Hash::check($validated['password_lama'], $user->password)) {
            throw ValidationException::withMessages([
                'password_lama' => ['Password saat ini salah.'],
            ]);
        }

        $user->update(['password' => $validated['password_baru']]); // auto-hashed via cast di User model

        // Cabut semua token supaya wajib login ulang di semua device
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil diperbarui. Silakan login kembali.']);
    }
}
