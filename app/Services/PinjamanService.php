<?php

namespace App\Services;

use App\Models\Kebijakan;
use App\Models\KasTransaksi;
use App\Models\Pinjaman;

class PinjamanService
{
    public static function pastikanTidakNumpukPinjamanAktif(Pinjaman $pinjaman): ?string
    {
        $query = Pinjaman::where('user_id', $pinjaman->user_id)
            ->where('status', 'DISETUJUI');

        if ($pinjaman->exists) {
            $query->where('id', '!=', $pinjaman->id);
        }

        if ($pinjaman->is_topup && $pinjaman->topup_dari_pinjaman_id) {
            $query->where('id', '!=', $pinjaman->topup_dari_pinjaman_id);
        }

        $bentrok = $query->first();

        if ($bentrok) {
            return 'Anggota ini sudah punya pinjaman aktif lain #' . $bentrok->kode . ' yang belum lunas/tergabung. Tidak bisa ACC pengajuan ini sampai itu diselesaikan (lunasi, atau ajukan ulang sebagai Top-Up).';
        }

        return null;
    }

    public static function pastikanKasCukup(float $jumlah): ?string
    {
        $saldoKas = KasTransaksi::saldoSaatIni();

        if ($jumlah > $saldoKas) {
            return 'Kas koperasi tidak mencukupi untuk mencairkan nominal ini. Saldo kas saat ini hanya Rp ' . number_format($saldoKas, 0, ',', '.') . '.';
        }

        return null;
    }

    public static function validasiSebelumDisetujui(Pinjaman $pinjaman): ?string
    {
        $errorNumpuk = static::pastikanTidakNumpukPinjamanAktif($pinjaman);
        if ($errorNumpuk) {
            return $errorNumpuk;
        }

        return static::pastikanKasCukup((float) $pinjaman->jumlah);
    }

    public static function defaultSukuBunga(): float
    {
        return (float) Kebijakan::current()->suku_bunga_persen;
    }
}
