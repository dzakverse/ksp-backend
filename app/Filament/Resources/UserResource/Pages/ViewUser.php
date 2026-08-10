<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Simpanan;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Data Diri')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('nama'),
                        TextEntry::make('nip')->label('NIP'),
                        TextEntry::make('nik')->label('NIK')->placeholder('-'),
                        TextEntry::make('jenis_kelamin')->label('Jenis Kelamin')->placeholder('-'),
                        TextEntry::make('tempat_lahir')->label('Tempat Lahir')->placeholder('-'),
                        TextEntry::make('tanggal_lahir')
                            ->label('Tanggal Lahir')
                            ->date('d M Y')
                            ->placeholder('-'),
                        TextEntry::make('role')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'SUPER_ADMIN' => 'Super Admin',
                                'KETUA' => 'Ketua',
                                'BENDAHARA' => 'Bendahara',
                                'ANGGOTA' => 'Anggota',
                                default => $state,
                            }),
                        TextEntry::make('status_keanggotaan')
                            ->label('Status')
                            ->badge()
                            ->color(fn (?string $state): string => $state === 'AKTIF' ? 'success' : 'danger'),
                        TextEntry::make('email')->placeholder('-'),
                        TextEntry::make('whatsapp')->placeholder('-'),
                        TextEntry::make('unit_kerja')->placeholder('-'),
                        TextEntry::make('tanggal_bergabung')
                            ->label('Bergabung Sejak')
                            ->date('d M Y')
                            ->placeholder('-'),
                        TextEntry::make('alamat')->columnSpanFull()->placeholder('-'),
                    ]),

                // Bagian saldo & pinjaman hanya relevan untuk role ANGGOTA
                Section::make('Saldo Simpanan')
                    ->visible(fn ($record): bool => $record->role === 'ANGGOTA')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('saldo_pokok')
                            ->label('Simpanan Pokok')
                            ->state(fn ($record) => self::totalSimpanan($record->id, 'POKOK'))
                            ->money('IDR', locale: 'id'),
                        TextEntry::make('saldo_wajib')
                            ->label('Simpanan Wajib')
                            ->state(fn ($record) => self::totalSimpanan($record->id, 'WAJIB'))
                            ->money('IDR', locale: 'id'),
                        TextEntry::make('saldo_sukarela')
                            ->label('Simpanan Sukarela')
                            ->state(fn ($record) => self::totalSimpanan($record->id, 'SUKARELA'))
                            ->money('IDR', locale: 'id'),
                        TextEntry::make('saldo_total')
                            ->label('Total Saldo')
                            ->weight('bold')
                            ->state(function ($record) {
                                return self::totalSimpanan($record->id, 'POKOK')
                                    + self::totalSimpanan($record->id, 'WAJIB')
                                    + self::totalSimpanan($record->id, 'SUKARELA');
                            })
                            ->money('IDR', locale: 'id'),
                    ]),

                Section::make('Pinjaman Aktif')
                    ->visible(fn ($record): bool => $record->role === 'ANGGOTA')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('pinjaman_kode')
                            ->label('Kode Pinjaman')
                            ->state(fn ($record) => $record->pinjamans()->where('status', 'DISETUJUI')->latest()->first()?->kode)
                            ->placeholder('Tidak ada pinjaman aktif'),
                        TextEntry::make('pinjaman_jumlah')
                            ->label('Jumlah Pinjaman')
                            ->state(fn ($record) => $record->pinjamans()->where('status', 'DISETUJUI')->latest()->first()?->jumlah)
                            ->money('IDR', locale: 'id')
                            ->placeholder('-'),
                        TextEntry::make('pinjaman_tenor')
                            ->label('Tenor')
                            ->state(function ($record) {
                                $tenor = $record->pinjamans()->where('status', 'DISETUJUI')->latest()->first()?->tenor_bulan;
                                return $tenor ? "{$tenor} Bulan" : null;
                            })
                            ->placeholder('-'),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
        ];
    }

    private static function totalSimpanan(int $userId, string $jenis): float
    {
        $setor = Simpanan::where('user_id', $userId)->where('jenis', $jenis)->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $tarik = Simpanan::where('user_id', $userId)->where('jenis', $jenis)->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');

        return (float) ($setor - $tarik);
    }
}
