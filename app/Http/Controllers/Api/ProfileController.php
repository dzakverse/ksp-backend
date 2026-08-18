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

    // PUT /api/profile -> pages/edit.jsx (Anggota mengedit data kontak miliknya sendiri)
    // Hanya field kontak yang boleh diubah mandiri; data identitas resmi (nama, NIK,
    // NIP, tanggal lahir, dst) tetap harus lewat Bendahara/Ketua/Super Admin.
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

    // POST /api/profile/foto -> upload/ganti foto profil milik akun yang sedang
    // login (Anggota, Bendahara, maupun Ketua - semua role pakai endpoint yang
    // sama karena ini foto profil DIRI SENDIRI, bukan punya orang lain).
    public function updateFoto(Request $request)
    {
        $validated = $request->validate([
            // 'image' memvalidasi file itu benar-benar gambar (bukan cuma cek
            // ekstensi), jadi file berbahaya yang disamarkan pakai nama .jpg
            // tetap ditolak. Dibatasi ke jpg/jpeg/png & maks 2MB sesuai yang
            // sudah ditulis di UI ("Format JPG, PNG. Ukuran maks. 2MB.").
            'foto' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $user = $request->user();

        // Hapus file foto lama dari storage (kalau memang foto lama itu hasil
        // upload lewat endpoint ini, ditandai dari path-nya), supaya file lama
        // tidak menumpuk terus di server tiap kali user ganti foto.
        if ($user->foto_url && str_contains($user->foto_url, '/storage/foto-profil/')) {
            $pathLama = ltrim(parse_url($user->foto_url, PHP_URL_PATH), '/');
            $pathLama = preg_replace('#^storage/#', '', $pathLama);
            Storage::disk('public')->delete($pathLama);
        }

        // store() otomatis generate nama file acak (hash) -> mencegah file
        // saling menimpa antar user & mencegah user mengontrol nama file
        // (path traversal / penamaan berbahaya).
        $path = $request->file('foto')->store('foto-profil', 'public');

        $user->update([
            'foto_url' => rtrim(config('app.url'), '/') . '/storage/' . $path,
        ]);

        return response()->json([
            'foto_url' => $user->foto_url,
        ]);
    }
}