<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use App\Filament\Resources\PinjamanResource;
use App\Services\PinjamanService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPinjaman extends EditRecord
{
    protected static string $resource = PinjamanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;
        $cicilanSudahAda = $record->cicilans()->exists();

        if ($cicilanSudahAda) {
            $jumlahBerubah = round((float) $data['jumlah'], 2) !== round((float) $record->jumlah, 2);
            $tenorBerubah = (int) $data['tenor_bulan'] !== (int) $record->tenor_bulan;

            if ($jumlahBerubah || $tenorBerubah) {
                Notification::make()
                    ->title('Tidak bisa disimpan')
                    ->body('Pinjaman ini sudah punya jadwal cicilan, jadi Jumlah / Tenor tidak bisa diubah dari sini - jadwal cicilan yang sudah ada tidak akan ikut ter-update dan datanya jadi tidak konsisten. Kalau anggota butuh tambahan pinjaman, gunakan alur Restrukturisasi.')
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        $akanJadiDisetujui = $data['status'] === 'DISETUJUI' && $record->status !== 'DISETUJUI';

        if ($akanJadiDisetujui) {
            $pinjamanSementara = $record->replicate();
            $pinjamanSementara->id = $record->id;
            $pinjamanSementara->jumlah = $data['jumlah'];

            $error = PinjamanService::validasiSebelumDisetujui($pinjamanSementara);

            if ($error) {
                Notification::make()
                    ->title('Tidak bisa disetujui')
                    ->body($error)
                    ->danger()
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        return $data;
    }
}
