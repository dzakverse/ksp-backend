<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cicilan extends Model
{
    use HasFactory;

    protected $table = 'cicilans';

    protected $fillable = [
        'pinjaman_id',
        'cicilan_ke',
        'jumlah',
        'jatuh_tempo',
        'tanggal_bayar',
        'status',
        'catatan',
        'dibayar_oleh',
    ];

    /**
     * Casting tipe data agar nilai rupiah & tanggal otomatis ter-parse dengan benar
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'jatuh_tempo' => 'date',
            'tanggal_bayar' => 'date',
        ];
    }

    /**
     * Relasi balik ke Pinjaman
     */
    public function pinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id');
    }

    public function dibayarOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibayar_oleh');
    }
}