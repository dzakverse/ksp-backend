<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName; // <-- Sudah ada
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser, HasName // <-- TAMBAHKAN HasName DI SINI
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nip', 
        'nama', 
        'password', 
        'role', 
        'id_anggota', 
        'tanggal_bergabung',
        'nik', 
        'tempat_lahir', 
        'tanggal_lahir', 
        'jenis_kelamin', 
        'alamat',
        'unit_kerja',
        'whatsapp', 
        'email', 
        'foto_url', 
        'id_keanggotaan', 
        'status_keanggotaan',
    ];

    protected $hidden = [
        'password', 
        'remember_token',
    ];

    /**
     * Casting tipe data versi modern Laravel 11/12
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'tanggal_bergabung' => 'date',
            'tanggal_lahir' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // 1. Tentukan Prefix berdasarkan Role
            $prefix = match ($user->role) {
                'BENDAHARA' => 'BDR',
                'KETUA' => 'KET',
                'SUPER_ADMIN' => 'ADM',
                default => 'ANG', // Default untuk ANGGOTA
            };

            // 2. Ambil Tahun Sekarang (Misal: 2026)
            $tahun = now()->format('Y');

            // 3. Format Prefix gabungan (contoh: "ANG-2026-")
            $prefixFull = "{$prefix}-{$tahun}-";

            // 4. Cari nomor urut terakhir pada tahun & prefix yang sama
            $lastUser = User::where('id_anggota', 'LIKE', "{$prefixFull}%")
                ->orderBy('id_anggota', 'desc')
                ->first();

            if ($lastUser && $lastUser->id_anggota) {
                // Ambil 3 digit angka terakhir (misal: "005" -> 5)
                $lastNumber = (int) substr($lastUser->id_anggota, -3);
                $nextNumber = $lastNumber + 1;
            } else {
                // Jika belum ada user di tahun ini, mulai dari 1
                $nextNumber = 1;
            }

            // 5. Format menjadi 3 digit angka dengan pad nol (misal: 1 -> 001)
            $user->id_anggota = $prefixFull . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
        });
    }

    public function getFilamentName(): string
    {
        return $this->nama ?? 'User KSP';
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    public function simpanans(): HasMany
    {
        return $this->hasMany(Simpanan::class, 'user_id');
    }

    public function pinjamans(): HasMany
    {
        return $this->hasMany(Pinjaman::class, 'user_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    public function isBendahara(): bool
    {
        return $this->role === 'BENDAHARA';
    }

    public function isKetua(): bool
    {
        return $this->role === 'KETUA';
    }

    public function isAnggota(): bool
    {
        return $this->role === 'ANGGOTA';
    }
}