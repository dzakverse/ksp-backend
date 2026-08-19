<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasTransaksi extends Model
{
    protected $table = 'kas_transaksis';

    protected $fillable = [
        'tipe',
        'jumlah',
        'catatan',
        'tanggal',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public static function saldoSaatIni(): float
    {
        $totalSimpanan = \App\Models\Simpanan::where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah')
            - \App\Models\Simpanan::where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
        $totalPinjamanAktif = \App\Models\Pinjaman::where('status', 'DISETUJUI')->sum('jumlah');
        $totalKasKeluar = static::where('tipe', 'KELUAR')->sum('jumlah');
        $totalKasMasuk = static::where('tipe', 'MASUK')->sum('jumlah');

        return (float) ($totalSimpanan - $totalPinjamanAktif + $totalKasMasuk - $totalKasKeluar);
    }
}
