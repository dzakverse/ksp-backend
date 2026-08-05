<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Simpanan extends Model
{
    use HasFactory;

    protected $table = 'simpanans';

    protected $fillable = [
        'user_id', 
        'jenis', 
        'tipe', 
        'jumlah', 
        'keterangan', 
        'status', 
        'tanggal',
        'created_by',
        'diproses_oleh',
    ];

    /**
     * Tipe data casting modern ala Laravel 11/12
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    /**
     * Relasi ke Anggota pemilik simpanan
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke Super Admin yang menginput simpanan
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke user (Bendahara/Ketua/Super Admin) yang mengeksekusi/konfirmasi
     * transaksi berstatus PENDING (mis. request tarik simpanan dari Anggota).
     */
    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}