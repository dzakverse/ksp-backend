<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Buat akun Super Admin Pertama untuk Login Filament
        User::firstOrCreate(
            ['nip' => '198501012024011001'],
            [
                'nama' => 'Super Admin KSP',
                'role' => 'SUPER_ADMIN',
                'email' => 'admin@ksp-dinsos.go.id',
                'password' => Hash::make('password123'), // Ganti dengan password yang diinginkan
                'status_keanggotaan' => 'AKTIF',
            ]
        );

        // Buat akun sampel Bendahara (Opsional untuk testing FE)
        User::firstOrCreate(
            ['nip' => '198802022024011002'],
            [
                'nama' => 'Bendahara KSP',
                'role' => 'BENDAHARA',
                'email' => 'bendahara@ksp-dinsos.go.id',
                'password' => Hash::make('password123'),
                'status_keanggotaan' => 'AKTIF',
            ]
        );

        // Buat akun sampel Ketua (Opsional untuk testing FE)
        User::firstOrCreate(
            ['nip' => '198203032024011003'],
            [
                'nama' => 'Ketua KSP',
                'role' => 'KETUA',
                'email' => 'ketua@ksp-dinsos.go.id',
                'password' => Hash::make('password123'),
                'status_keanggotaan' => 'AKTIF',
            ]
        );

        // Buat akun sampel Anggota (agar DetailAnggota/Simpanan/Pinjaman ada datanya saat testing)
        $anggota = User::firstOrCreate(
            ['nip' => '199001012024011004'],
            [
                'nama' => 'Budi Santoso',
                'role' => 'ANGGOTA',
                'id_anggota' => 'ANG-2024-001',
                'id_keanggotaan' => 'KSP-2024-0891',
                'unit_kerja' => 'Sekretariat',
                'tanggal_bergabung' => '2024-01-15',
                'email' => 'budi.santoso@email.com',
                'password' => Hash::make('password123'),
                'status_keanggotaan' => 'AKTIF',
            ]
        );

        if ($anggota->simpanans()->count() === 0) {
            $anggota->simpanans()->create(['jenis' => 'POKOK', 'tipe' => 'SETOR', 'jumlah' => 500000, 'tanggal' => '2024-01-15', 'keterangan' => 'Setoran awal keanggotaan']);
            $anggota->simpanans()->create(['jenis' => 'WAJIB', 'tipe' => 'SETOR', 'jumlah' => 250000, 'tanggal' => now()->toDateString(), 'keterangan' => 'Simpanan wajib bulan berjalan']);
            $anggota->simpanans()->create(['jenis' => 'SUKARELA', 'tipe' => 'SETOR', 'jumlah' => 1000000, 'tanggal' => now()->toDateString()]);
        }

        if ($anggota->pinjamans()->count() === 0) {
            $anggota->pinjamans()->create([
                'kode' => 'LN-2026-001',
                'jumlah' => 5000000,
                'tenor_bulan' => 12,
                'alasan' => 'Kebutuhan mendesak',
                'status' => 'MENUNGGU',
            ]);
        }

        // Nilai default Kendali Kebijakan (Ketua)
        \App\Models\Kebijakan::firstOrCreate([]);
    }
}