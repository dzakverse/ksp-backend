<?php

namespace App\Filament\Resources\KasTransaksiResource\Pages;

use App\Filament\Resources\KasTransaksiResource;
use App\Models\KasTransaksi;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateKasTransaksi extends CreateRecord
{
    protected static string $resource = KasTransaksiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['tipe'] ?? null) === 'KELUAR') {
            $saldoSaatIni = KasTransaksi::saldoSaatIni();

            if ((float) $data['jumlah'] > $saldoSaatIni) {
                Notification::make()
                    ->title('Tidak bisa disimpan')
                    ->body('Kas koperasi tidak mencukupi. Saldo kas saat ini hanya Rp ' . number_format($saldoSaatIni, 0, ',', '.') . '.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
