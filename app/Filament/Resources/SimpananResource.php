<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SimpananResource\Pages;
use App\Models\Simpanan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SimpananResource extends Resource
{
    protected static ?string $model = Simpanan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?string $navigationLabel = 'Simpanan';

    protected static ?string $modelLabel = 'Simpanan';

    protected static ?string $pluralModelLabel = 'Simpanan';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Pencatatan Simpanan')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('user_id')
                                ->label('Anggota')
                                ->options(fn (): array => User::where('role', 'anggota')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->columnSpanFull(),
                            Select::make('jenis')
                                ->label('Jenis Simpanan')
                                ->options([
                                    'pokok' => 'Simpanan Pokok',
                                    'wajib' => 'Simpanan Wajib',
                                    'sukarela' => 'Simpanan Sukarela',
                                ])
                                ->required(),
                            TextInput::make('jumlah')
                                ->label('Jumlah (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0),
                            DatePicker::make('tanggal')
                                ->label('Tanggal')
                                ->required()
                                ->default(now()),
                            Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(2)
                                ->placeholder('Contoh: Simpanan wajib bulan Januari 2026'),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Anggota')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jenis')
                    ->label('Jenis')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pokok' => 'success',
                        'wajib' => 'warning',
                        'sukarela' => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pokok' => 'Pokok',
                        'wajib' => 'Wajib',
                        'sukarela' => 'Sukarela',
                    }),
                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(50),
                TextColumn::make('created_at')
                    ->label('Dicatat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis Simpanan')
                    ->options([
                        'pokok' => 'Pokok',
                        'wajib' => 'Wajib',
                        'sukarela' => 'Sukarela',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Anggota')
                    ->options(fn (): array => User::where('role', 'anggota')
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable(),
                Tables\Filters\Filter::make('tanggal')
                    ->label('Periode')
                    ->form([
                        DatePicker::make('dari')
                            ->label('Dari Tanggal'),
                        DatePicker::make('sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['dari'], fn (Builder $query, string $date): Builder => $query->where('tanggal', '>=', $date))
                            ->when($data['sampai'], fn (Builder $query, string $date): Builder => $query->where('tanggal', '<=', $date));
                    }),
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
            'index' => Pages\ListSimpanans::route('/'),
            'create' => Pages\CreateSimpanan::route('/create'),
            'edit' => Pages\EditSimpanan::route('/{record}/edit'),
        ];
    }
}
