<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cicilan extends Model
{
    protected $fillable = [
        'pinjaman_id', 'cicilan_ke', 'jumlah', 'jatuh_tempo', 'tanggal_bayar', 'status',
    ];

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class);
    }
}
