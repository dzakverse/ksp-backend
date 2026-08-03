<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PinjamanResource\Pages;
use App\Models\Pinjaman;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PinjamanResource extends Resource
{
    protected static ?string $model = Pinjaman::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Pinjaman';

    protected static ?string $modelLabel = 'Pinjaman';

    protected static ?string $pluralModelLabel = 'Pinjaman';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Data Pinjaman')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('kode')
                                ->label('Kode Pinjaman')
                                ->default(fn () => 'LN-' . date('Ymd') . '-' . strtoupper(Str::random(4)))
                                ->readOnly()
                                ->required(),

                            Select::make('user_id')
                                ->label('Anggota Pemohon')
                                ->options(fn (): array => User::where('role', 'ANGGOTA')
                                    ->orderBy('nama')
                                    ->pluck('nama', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required(),

                            TextInput::make('jumlah')
                                ->label('Jumlah Pinjaman (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0)
                                ->reactive()
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state): void {
                                    $tenor = (int) $get('tenor_bulan');
                                    $bunga = (float) $get('suku_bunga_persen');
                                    $jumlah = (float) $state;
                                    if ($tenor > 0) {
                                        $totalBunga = $jumlah * ($bunga / 100) * ($tenor / 12);
                                        $angsuran = ($jumlah + $totalBunga) / $tenor;
                                        $set('angsuran_per_bulan', round($angsuran));
                                    }
                                }),

                            TextInput::make('tenor_bulan')
                                ->label('Tenor (Bulan)')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(120)
                                ->reactive()
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state): void {
                                    $jumlah = (float) $get('jumlah');
                                    $bunga = (float) $get('suku_bunga_persen');
                                    $tenor = (int) $state;
                                    if ($tenor > 0 && $jumlah > 0) {
                                        $totalBunga = $jumlah * ($bunga / 100) * ($tenor / 12);
                                        $angsuran = ($jumlah + $totalBunga) / $tenor;
                                        $set('angsuran_per_bulan', round($angsuran));
                                    }
                                }),

                            TextInput::make('suku_bunga_persen')
                                ->label('Suku Bunga (% per tahun)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(100)
                                ->reactive()
                                ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state): void {
                                    $jumlah = (float) $get('jumlah');
                                    $tenor = (int) $get('tenor_bulan');
                                    $bunga = (float) $state;
                                    if ($tenor > 0 && $jumlah > 0) {
                                        $totalBunga = $jumlah * ($bunga / 100) * ($tenor / 12);
                                        $angsuran = ($jumlah + $totalBunga) / $tenor;
                                        $set('angsuran_per_bulan', round($angsuran));
                                    }
                                }),

                            TextInput::make('angsuran_per_bulan')
                                ->label('Angsuran per Bulan (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0)
                                ->readOnly(),

                            Select::make('status')
                                ->label('Status Pinjaman')
                                ->options([
                                    'MENUNGGU' => 'Menunggu Persetujuan',
                                    'DISETUJUI_BENDAHARA' => 'Disetujui Bendahara',
                                    'DISETUJUI' => 'Disetujui Final',
                                    'DITOLAK' => 'Ditolak',
                                    'LUNAS' => 'Lunas',
                                ])
                                ->required()
                                ->default('MENUNGGU'),

                            Textarea::make('alasan')
                                ->label('Alasan / Keterangan')
                                ->rows(3)
                                ->columnSpanFull(),

                            Forms\Components\Hidden::make('created_by')
                                ->default(fn () => auth()->id()),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('user.nama')
                    ->label('Anggota')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('jumlah')
                    ->label('Jumlah Pinjaman')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('tenor_bulan')
                    ->label('Tenor')
                    ->suffix(' bln')
                    ->sortable(),

                TextColumn::make('angsuran_per_bulan')
                    ->label('Angsuran/Bln')
                    ->money('IDR', locale: 'id'),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'MENUNGGU',
                        'info' => 'DISETUJUI_BENDAHARA',
                        'success' => 'DISETUJUI',
                        'danger' => 'DITOLAK',
                        'primary' => 'LUNAS',
                    ]),

                IconColumn::make('is_bypassed')
                    ->label('Bypass')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'MENUNGGU' => 'Menunggu',
                        'DISETUJUI_BENDAHARA' => 'Disetujui Bendahara',
                        'DISETUJUI' => 'Disetujui Final',
                        'DITOLAK' => 'Ditolak',
                        'LUNAS' => 'Lunas',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Anggota')
                    ->options(fn (): array => User::where('role', 'ANGGOTA')
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
                        ->toArray())
                    ->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // ACTION BYPASS APPROVAL UNTUK SUPER ADMIN
                Tables\Actions\Action::make('bypassApproval')
                    ->label('Bypass Approval')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Bypass Approval Pinjaman')
                    ->modalDescription('Anda yakin ingin menyetujui pinjaman ini secara langsung?')
                    ->modalSubmitActionLabel('Ya, Setujui')
                    ->visible(fn (Pinjaman $record): bool => in_array($record->status, ['MENUNGGU', 'DISETUJUI_BENDAHARA']))
                    ->action(function (Pinjaman $record): void {
                        $record->update([
                            'status' => 'DISETUJUI',
                            'is_bypassed' => true,
                            'bypassed_by' => auth()->id(),
                            'diverifikasi_oleh' => auth()->id(),
                            'catatan_verifikasi' => 'Disetujui via Bypass Super Admin',
                        ]);

                        Notification::make()
                            ->title('Pinjaman berhasil disetujui')
                            ->body("Pinjaman untuk {$record->user->nama} telah disetujui langsung (bypass).")
                            ->success()
                            ->send();
                    }),

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
            'index' => Pages\ListPinjamans::route('/'),
            'create' => Pages\CreatePinjaman::route('/create'),
            'edit' => Pages\EditPinjaman::route('/{record}/edit'),
        ];
    }
}