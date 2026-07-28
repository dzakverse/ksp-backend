<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
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

    /**
     * Interface Method dari FilamentUser:
     * Menentukan akun mana yang boleh mengakses Panel Super Admin Filament
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === 'SUPER_ADMIN';
    }

    /**
     * Relasi ke Tabel Simpanan
     */
    public function simpanans(): HasMany
    {
        return $this->hasMany(Simpanan::class, 'user_id');
    }

    /**
     * Relasi ke Tabel Pinjaman
     */
    public function pinjamans(): HasMany
    {
        return $this->hasMany(Pinjaman::class, 'user_id');
    }

    /**
     * Helper Role Checking
     */
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