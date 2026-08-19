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
        'suku_bunga_persen',    
        'angsuran_per_bulan',  
        'alasan',
        'status', 
        'diverifikasi_oleh', 
        'catatan_verifikasi',
        'bukti_pendukung',
        'is_bypassed',
        'bypassed_by',
        'created_by',
        'pinjaman_lama_id',
        'is_restrukturisasi',
        'sisa_pokok_lama',
        'sisa_pokok',           
        'is_topup',
        'topup_dari_pinjaman_id',
        'potongan_pelunasan',
        'jumlah_pencairan_bersih',
    ];

    public static function generateKode(): string
    {
        $prefix = 'LN-' . now()->format('Y') . '-';

        $nomorTerakhir = static::where('kode', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(kode, ?) AS UNSIGNED)) as nomor_max', [strlen($prefix) + 1])
            ->value('nomor_max');

        $nomorBerikutnya = ((int) $nomorTerakhir) + 1;

        return $prefix . str_pad($nomorBerikutnya, 3, '0', STR_PAD_LEFT);
    }

    protected static function booted(): void
    {
        static::saved(function (Pinjaman $pinjaman) {
            if ($pinjaman->status === 'DISETUJUI') {
                $pinjaman->generateCicilanSchedule();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'suku_bunga_persen' => 'decimal:2',
            'angsuran_per_bulan' => 'decimal:2',
            'is_bypassed' => 'boolean',
            'is_restrukturisasi' => 'boolean',
            'sisa_pokok_lama' => 'decimal:2',
            'sisa_pokok' => 'decimal:2',
            'is_topup' => 'boolean',
            'potongan_pelunasan' => 'decimal:2',
            'jumlah_pencairan_bersih' => 'decimal:2',
        ];
    }

    public function topupDariPinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'topup_dari_pinjaman_id');
    }

    public function pinjamanLama(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_lama_id');
    }

    public function pinjamanBaru(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Pinjaman::class, 'pinjaman_lama_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function bypassedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bypassed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function cicilans(): HasMany
    {
        return $this->hasMany(Cicilan::class, 'pinjaman_id');
    }

    public function generateCicilanSchedule(): void
    {
        if ($this->cicilans()->exists()) {
            return;
        }

        $bungaPersen = $this->suku_bunga_persen ?? Kebijakan::current()->suku_bunga_persen;
        $pokokBulanan = $this->jumlah / $this->tenor_bulan;
        $bungaBulanan = $this->jumlah * ($bungaPersen / 100);
        $angsuran = $this->angsuran_per_bulan ?? round($pokokBulanan + $bungaBulanan);

        $tanggalDasar = now();

        for ($i = 1; $i <= $this->tenor_bulan; $i++) {
            $this->cicilans()->create([
                'cicilan_ke' => $i,
                'jumlah' => $angsuran,
                'jatuh_tempo' => $tanggalDasar->copy()->addMonths($i)->toDateString(),
                'status' => 'BELUM_BAYAR',
            ]);
        }
    }
}