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
    }
}