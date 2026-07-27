<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pinjaman extends Model
{
    protected $table = 'pinjamans'; 

    protected $fillable = [
        'kode', 'user_id', 'jumlah', 'tenor_bulan', 'alasan',
        'status', 'diverifikasi_oleh', 'catatan_verifikasi',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function cicilans()
    {
        return $this->hasMany(Cicilan::class, 'pinjaman_id');
    }
}