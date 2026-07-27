<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

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
            'alamat' => $user->alamat,
            'whatsapp' => $user->whatsapp,
            'email' => $user->email,
            'foto_url' => $user->foto_url,
            'tanggal_bergabung' => $user->tanggal_bergabung,
        ]);
    }
}
