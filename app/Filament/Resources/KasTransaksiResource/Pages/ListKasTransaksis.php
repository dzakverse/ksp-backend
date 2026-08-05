<?php

namespace App\Filament\Resources\KasTransaksiResource\Pages;

use App\Filament\Resources\KasTransaksiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKasTransaksis extends ListRecords
{
    protected static string $resource = KasTransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
