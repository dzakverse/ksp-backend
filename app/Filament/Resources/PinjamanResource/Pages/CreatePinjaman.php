<?php

namespace App\Filament\Resources\PinjamanResource\Pages;

use App\Filament\Resources\PinjamanResource;
use App\Models\Pinjaman;
use App\Services\PinjamanService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePinjaman extends CreateRecord
{
    protected static string $resource = PinjamanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sisa_pokok'] = $data['jumlah'];
        $data['suku_bunga_persen'] = $data['suku_bunga_persen'] !== null && $data['suku_bunga_persen'] !== ''
            ? $data['suku_bunga_persen']
            : PinjamanService::defaultSukuBunga();
        $data['created_by'] = auth()->id();

        if (($data['status'] ?? null) === 'DISETUJUI') {
            $pinjamanSementara = new Pinjaman([
                'user_id' => $data['user_id'],
                'jumlah' => $data['jumlah'],
            ]);

            $error = PinjamanService::validasiSebelumDisetujui($pinjamanSementara);

            if ($error) {
                Notification::make()
                    ->title('Tidak bisa dibuat')
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
