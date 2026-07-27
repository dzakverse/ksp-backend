<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
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

    protected $casts = [
        'password' => 'hashed',
        'tanggal_bergabung' => 'date', // Otomatis diparse sebagai objek Carbon
        'tanggal_lahir' => 'date',
    ];

    /**
     * Relasi ke Tabel Simpanan
     */
    public function simpanans()
    {
        return $this->hasMany(Simpanan::class, 'user_id');
    }

    /**
     * Relasi ke Tabel Pinjaman
     */
    public function pinjamans()
    {
        return $this->hasMany(Pinjaman::class, 'user_id');
    }

    /**
     * Helper Role Checking
     */
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