<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    // GET /api/profile -> pages/profil.jsx
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'nama' => $user->nama,
            'id_anggota' => $user->id_anggota,
            'id_keanggotaan' => $user->id_keanggotaan,
            'status_keanggotaan' => $user->status_keanggotaan,
            'nik' => $user->nik,
            'nip' => $user->nip,
            'tempat_lahir' => $user->tempat_lahir,
            'tanggal_lahir' => $user->tanggal_lahir,
            'jenis_kelamin' => $user->jenis_kelamin,
            'unit_kerja' => $user->unit_kerja,
            'alamat' => $user->alamat,
            'whatsapp' => $user->whatsapp,
            'email' => $user->email,
            'foto_url' => $user->foto_url,
            'tanggal_bergabung' => $user->tanggal_bergabung,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'whatsapp' => 'nullable|string|max:30',
            'alamat' => 'nullable|string',
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json($user->fresh());
    }

    public function updateFoto(Request $request)
    {
        $validated = $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        if ($user->foto_url && str_contains($user->foto_url, '/storage/foto-profil/')) {
            $pathLama = ltrim(parse_url($user->foto_url, PHP_URL_PATH), '/');
            $pathLama = preg_replace('#^storage/#', '', $pathLama);
            Storage::disk('public')->delete($pathLama);
        }

        $path = $request->file('foto')->store('foto-profil', 'public');

        $user->update([
            'foto_url' => rtrim(config('app.url'), '/') . '/storage/' . $path,
        ]);

        return response()->json([
            'foto_url' => $user->foto_url,
        ]);
    }

    public function hapusFoto(Request $request)
    {
        $user = $request->user();

        if ($user->foto_url && str_contains($user->foto_url, '/storage/foto-profil/')) {
            $path = ltrim(parse_url($user->foto_url, PHP_URL_PATH), '/');
            $path = preg_replace('#^storage/#', '', $path);
            Storage::disk('public')->delete($path);
        }

        $user->update(['foto_url' => null]);

        return response()->json(['foto_url' => null]);
    }
}