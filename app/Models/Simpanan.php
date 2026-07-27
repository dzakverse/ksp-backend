<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Simpanan extends Model
{
    protected $fillable = [
        'user_id', 'jenis', 'tipe', 'jumlah', 'keterangan', 'status', 'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
