<?php

namespace App\Filament\Resources\KasTransaksiResource\Pages;

use App\Filament\Resources\KasTransaksiResource;
use Filament\Actions;
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
}
