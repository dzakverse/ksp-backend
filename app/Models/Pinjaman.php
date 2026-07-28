<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pinjaman extends Model
{
    use HasFactory;

    protected $table = 'pinjamans'; 

    protected $fillable = [
        'kode', 
        'user_id', 
        'jumlah', 
        'tenor_bulan', 
        'suku_bunga_persen',    // Ditambahkan dari versi uji coba (opsional)
        'angsuran_per_bulan',   // Ditambahkan dari versi uji coba (opsional)
        'alasan',
        'status', 
        'diverifikasi_oleh', 
        'catatan_verifikasi',
        'bukti_pendukung',      // Ditambahkan jika ada upload berkas dari FE
        'is_bypassed',
        'bypassed_by',
        'created_by',
    ];

    /**
     * Casting tipe data desimal agar angka rupiah & persen presisi di Filament
     */
    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'suku_bunga_persen' => 'decimal:2',
            'angsuran_per_bulan' => 'decimal:2',
            'is_bypassed' => 'boolean',
        ];
    }

    /**
     * Relasi ke Anggota Peminjam
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi ke Bendahara / Ketua yang memverifikasi di FE
     */
    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    /**
     * Relasi ke Super Admin yang melakukan Bypass
     */
    public function bypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bypassed_by');
    }

    /**
     * Relasi ke Super Admin yang membuatkan draf pinjaman
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Tabel Cicilan / Angsuran
     */
    public function cicilans(): HasMany
    {
        return $this->hasMany(Cicilan::class, 'pinjaman_id');
    }
}