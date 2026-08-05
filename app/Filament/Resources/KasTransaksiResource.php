<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KasTransaksiResource\Pages;
use App\Models\KasTransaksi;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class KasTransaksiResource extends Resource
{
    protected static ?string $model = KasTransaksi::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Kas Koperasi';

    protected static ?string $modelLabel = 'Transaksi Kas';

    protected static ?string $pluralModelLabel = 'Kas Koperasi';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Catat Transaksi Kas')
                    ->description('Gunakan ini untuk mencatat pengeluaran kas koperasi untuk kebutuhan operasional (mis. ATK, sewa, konsumsi rapat, dll).')
                    ->schema([
                        Select::make('tipe')
                            ->label('Tipe')
                            ->options([
                                'KELUAR' => 'Kas Keluar (Pengeluaran)',
                                'MASUK' => 'Kas Masuk (Pemasukan lain-lain)',
                            ])
                            ->default('KELUAR')
                            ->required(),

                        TextInput::make('jumlah')
                            ->label('Jumlah (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->minValue(1),

                        DatePicker::make('tanggal')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),

                        Textarea::make('catatan')
                            ->label('Catatan (Wajib)')
                            ->required()
                            ->rows(3)
                            ->placeholder('Contoh: Pembelian ATK kantor untuk kebutuhan administrasi bulan Agustus 2026')
                            ->columnSpanFull(),

                        Forms\Components\Hidden::make('dicatat_oleh')
                            ->default(fn () => auth()->id()),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('tipe')
                    ->label('Tipe')
                    ->colors([
                        'danger' => 'KELUAR',
                        'success' => 'MASUK',
                    ])
                    ->formatStateUsing(fn (string $state): string => $state === 'KELUAR' ? 'Kas Keluar' : 'Kas Masuk'),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('catatan')
                    ->label('Catatan')
                    ->limit(50),

                TextColumn::make('dicatatOleh.nama')
                    ->label('Dicatat Oleh')
                    ->placeholder('-'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tipe')
                    ->options([
                        'KELUAR' => 'Kas Keluar',
                        'MASUK' => 'Kas Masuk',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKasTransaksis::route('/'),
            'create' => Pages\CreateKasTransaksi::route('/create'),
            'edit' => Pages\EditKasTransaksi::route('/{record}/edit'),
        ];
    }
}
