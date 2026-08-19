<?php

namespace App\Filament\Resources\KasTransaksiResource\Pages;

use App\Filament\Resources\KasTransaksiResource;
use App\Models\KasTransaksi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditKasTransaksi extends EditRecord
{
    protected static string $resource = KasTransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['tipe'] ?? null) === 'KELUAR') {
            $saldoSaatIni = KasTransaksi::saldoSaatIni();

            $saldoTanpaBarisIni = $this->record->tipe === 'KELUAR'
                ? $saldoSaatIni + (float) $this->record->jumlah
                : $saldoSaatIni - (float) $this->record->jumlah;

            if ((float) $data['jumlah'] > $saldoTanpaBarisIni) {
                Notification::make()
                    ->title('Tidak bisa disimpan')
                    ->body('Kas koperasi tidak mencukupi untuk nominal ini. Saldo kas yang tersedia (di luar transaksi ini) hanya Rp ' . number_format($saldoTanpaBarisIni, 0, ',', '.') . '.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
