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

    public function diprosesOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public static function breakdownSaldo(int $userId): array
    {
        $rows = static::where('user_id', $userId)
            ->where('status', 'BERHASIL')
            ->selectRaw('jenis, tipe, SUM(jumlah) as total')
            ->groupBy('jenis', 'tipe')
            ->get();

        return static::hitungBreakdownDariRows($rows);
    }

    public static function breakdownSaldoSemua(): array
    {
        $rows = static::where('status', 'BERHASIL')
            ->selectRaw('jenis, tipe, SUM(jumlah) as total')
            ->groupBy('jenis', 'tipe')
            ->get();

        return static::hitungBreakdownDariRows($rows);
    }

    /**
     * Versi agregat untuk BANYAK user sekaligus, dikelompokkan per user_id.
     * Dipakai AnggotaController::index() supaya saldo untuk 1 halaman tabel
     * anggota (mis. 10-50 baris) cukup 1 query total, bukan 4 query x N baris
     * (N+1) seperti sebelumnya.
     *
     * @return \Illuminate\Support\Collection<int, array{pokok:float,wajib:float,sukarela:float,total:float}>
     */
    public static function breakdownSaldoBanyakUser(iterable $userIds): \Illuminate\Support\Collection
    {
        $rowsPerUser = static::whereIn('user_id', $userIds)
            ->where('status', 'BERHASIL')
            ->selectRaw('user_id, jenis, tipe, SUM(jumlah) as total')
            ->groupBy('user_id', 'jenis', 'tipe')
            ->get()
            ->groupBy('user_id');

        return collect($userIds)->mapWithKeys(fn ($userId) => [
            $userId => static::hitungBreakdownDariRows($rowsPerUser->get($userId, collect())),
        ]);
    }

    private static function hitungBreakdownDariRows(iterable $rows): array
    {
        $rows = collect($rows);

        $net = function (string $jenis) use ($rows): float {
            $setor = (float) optional($rows->first(fn ($r) => $r->jenis === $jenis && $r->tipe === 'SETOR'))->total;
            $tarik = (float) optional($rows->first(fn ($r) => $r->jenis === $jenis && $r->tipe === 'TARIK'))->total;
            return $setor - $tarik;
        };

        $pokok = $net('POKOK');
        $wajib = $net('WAJIB');
        $sukarela = $net('SUKARELA');

        return [
            'pokok' => $pokok,
            'wajib' => $wajib,
            'sukarela' => $sukarela,
            'total' => $pokok + $wajib + $sukarela,
        ];
    }
}