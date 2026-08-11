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
        'pinjaman_lama_id',
        'is_restrukturisasi',
        'sisa_pokok_lama',
        'sisa_pokok',           // Kolom NOT NULL di DB -> wajib ada di fillable,
                                // kalau tidak, Eloquent diam-diam membuang nilainya
                                // saat create() dan insert gagal (500).
        'is_topup',
        'topup_dari_pinjaman_id',
        'potongan_pelunasan',
        'jumlah_pencairan_bersih',
    ];

    /**
     * Generate kode pinjaman berurutan per-tahun (mis. LN-2026-009).
     *
     * PENTING: angka urut diambil dari nilai TERTINGGI yang pernah dipakai
     * (bukan dari jumlah baris/count()), supaya tidak collide dengan kode
     * yang masih ada begitu ada pinjaman lain yang dihapus (mis. lewat
     * Filament). count()+1 akan "mundur" dan menabrak kode existing kalau
     * ada baris di tengah yang dihapus - generateKode() ini aman dari itu.
     */
    public static function generateKode(): string
    {
        $prefix = 'LN-' . now()->format('Y') . '-';

        $nomorTerakhir = static::where('kode', 'like', $prefix . '%')
            ->selectRaw('MAX(CAST(SUBSTRING(kode, ?) AS UNSIGNED)) as nomor_max', [strlen($prefix) + 1])
            ->value('nomor_max');

        $nomorBerikutnya = ((int) $nomorTerakhir) + 1;

        return $prefix . str_pad($nomorBerikutnya, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Auto-generate jadwal cicilan begitu status pinjaman menjadi DISETUJUI,
     * dari jalur MANAPUN (alur normal verifikasi/persetujuan, Emergency Bypass,
     * ATAU input langsung oleh Super Admin lewat panel Filament). Tanpa hook ini,
     * pinjaman yang dibuat langsung berstatus DISETUJUI oleh Super Admin tidak
     * akan pernah punya cicilan, sehingga tracking-nya tidak muncul di halaman
     * manapun (Anggota, Bendahara, Ketua). generateCicilanSchedule() sendiri
     * idempotent (skip kalau cicilan sudah ada), jadi aman dipanggil berkali-kali.
     */
    protected static function booted(): void
    {
        static::saved(function (Pinjaman $pinjaman) {
            if ($pinjaman->status === 'DISETUJUI') {
                $pinjaman->generateCicilanSchedule();
            }
        });
    }

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
            'is_restrukturisasi' => 'boolean',
            'sisa_pokok_lama' => 'decimal:2',
            'sisa_pokok' => 'decimal:2',
            'is_topup' => 'boolean',
            'potongan_pelunasan' => 'decimal:2',
            'jumlah_pencairan_bersih' => 'decimal:2',
        ];
    }

    /**
     * Relasi ke pinjaman lama yang jadi dasar Top-Up (diajukan mandiri oleh Anggota
     * lewat ajukan.jsx saat masih punya pinjaman aktif) - beda dari pinjamanLama()
     * yang khusus jalur Restrukturisasi manual oleh Ketua.
     */
    public function topupDariPinjaman(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'topup_dari_pinjaman_id');
    }

    /**
     * Relasi ke pinjaman lama yang digabung/direstrukturisasi ke pinjaman ini.
     */
    public function pinjamanLama(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_lama_id');
    }

    /**
     * Relasi ke pinjaman baru hasil restrukturisasi dari pinjaman ini (jika ada).
     */
    public function pinjamanBaru(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Pinjaman::class, 'pinjaman_lama_id');
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

    /**
     * Generate jadwal cicilan otomatis (dipanggil sekali saat status berubah
     * menjadi DISETUJUI, baik lewat alur normal maupun Emergency Bypass).
     * Idempotent: tidak akan generate ulang kalau sudah pernah ada cicilan.
     */
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