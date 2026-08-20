<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SimpananResource\Pages;
use App\Models\Kebijakan;
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
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
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
                                ->options(fn (): array => User::where('role', 'ANGGOTA')
                                    ->orderBy('nama')
                                    ->pluck('nama', 'id')
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpanFull(),

                            Select::make('jenis')
                                ->label('Jenis Simpanan')
                                ->options([
                                    'POKOK' => 'Simpanan Pokok',
                                    'WAJIB' => 'Simpanan Wajib',
                                    'SUKARELA' => 'Simpanan Sukarela',
                                ])
                                ->live()
                                ->required(),

                            Select::make('tipe')
                                ->label('Tipe Transaksi')
                                ->options([
                                    'SETOR' => 'Setor',
                                    'TARIK' => 'Tarik',
                                ])
                                ->default('SETOR')
                                ->live()
                                ->required(),

                            TextInput::make('jumlah')
                                ->label('Jumlah (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required()
                                ->minValue(0)
                                ->helperText(function (Get $get): ?string {
                                    if ($get('tipe') === 'TARIK') {
                                        if (! $get('user_id') || ! $get('jenis')) {
                                            return null;
                                        }
                                        $saldo = self::saldoTersedia((int) $get('user_id'), $get('jenis'));
                                        return 'Saldo ' . $get('jenis') . ' tersedia saat ini: Rp ' . number_format($saldo, 0, ',', '.');
                                    }

                                    $minimal = self::minimalSetoran($get('jenis'));
                                    if ($minimal === null) {
                                        return null;
                                    }
                                    return 'Minimal setoran ' . $get('jenis') . ' sesuai kebijakan: Rp ' . number_format($minimal, 0, ',', '.');
                                })
                                ->rules([
                                    fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if ($get('tipe') === 'TARIK') {
                                            if (! $get('user_id') || ! $get('jenis')) {
                                                return;
                                            }
                                            $saldo = self::saldoTersedia((int) $get('user_id'), $get('jenis'));
                                            if ((float) $value > $saldo) {
                                                $fail('Jumlah melebihi saldo tersedia (Rp ' . number_format($saldo, 0, ',', '.') . ').');
                                            }
                                            return;
                                        }

                                        // SETOR: nominal tidak boleh di bawah minimal kebijakan
                                        // untuk simpanan pokok/wajib yang ditetapkan Ketua.
                                        $minimal = self::minimalSetoran($get('jenis'));
                                        if ($minimal !== null && (float) $value < $minimal) {
                                            $fail('Jumlah setoran ' . $get('jenis') . ' minimal Rp ' . number_format($minimal, 0, ',', '.') . ' sesuai kebijakan.');
                                        }
                                    },
                                ]),

                            DatePicker::make('tanggal')
                                ->label('Tanggal Transaksi')
                                ->required()
                                ->default(now()),

                            Select::make('status')
                                ->label('Status Transaksi')
                                ->options([
                                    'BERHASIL' => 'Berhasil',
                                    'PENDING' => 'Pending',
                                    'GAGAL' => 'Gagal',
                                ])
                                ->default('BERHASIL')
                                ->required(),

                            Textarea::make('keterangan')
                                ->label('Keterangan')
                                ->rows(2)
                                ->placeholder('Contoh: Simpanan wajib bulan Juli 2026')
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
                TextColumn::make('user.nama')
                    ->label('Anggota')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('jenis')
                    ->label('Jenis')
                    ->colors([
                        'primary' => 'POKOK',
                        'warning' => 'WAJIB',
                        'success' => 'SUKARELA',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'POKOK' => 'Pokok',
                        'WAJIB' => 'Wajib',
                        'SUKARELA' => 'Sukarela',
                        default => $state,
                    }),

                BadgeColumn::make('tipe')
                    ->label('Tipe')
                    ->colors([
                        'success' => 'SETOR',
                        'danger' => 'TARIK',
                    ]),

                TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->money('IDR', locale: 'id')
                    ->sortable(),

                TextColumn::make('tanggal')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'BERHASIL',
                        'warning' => 'PENDING',
                        'danger' => 'GAGAL',
                    ]),

                TextColumn::make('keterangan')
                    ->label('Keterangan')
                    ->limit(30)
                    ->placeholder('-'),

                TextColumn::make('createdBy.nama')
                    ->label('Dicatat Oleh')
                    ->placeholder('Sistem / Mandiri'),
            ])
            ->defaultSort('tanggal', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('jenis')
                    ->label('Jenis Simpanan')
                    ->options([
                        'POKOK' => 'Pokok',
                        'WAJIB' => 'Wajib',
                        'SUKARELA' => 'Sukarela',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Anggota')
                    ->options(fn (): array => User::where('role', 'ANGGOTA')
                        ->orderBy('nama')
                        ->pluck('nama', 'id')
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

    /**
     * Ambil nominal minimal setoran dari kebijakan aktif untuk jenis POKOK/WAJIB.
     * SUKARELA tidak punya batas minimal (sifatnya sukarela).
     */
    private static function minimalSetoran(?string $jenis): ?float
    {
        if (! in_array($jenis, ['POKOK', 'WAJIB'], true)) {
            return null;
        }

        $kebijakan = Kebijakan::current();

        return $jenis === 'POKOK'
            ? (float) $kebijakan->simpanan_pokok_nominal
            : (float) $kebijakan->simpanan_wajib_nominal;
    }

    private static function saldoTersedia(int $userId, string $jenis): float
    {
        $setor = Simpanan::where('user_id', $userId)->where('jenis', $jenis)->where('tipe', 'SETOR')->where('status', 'BERHASIL')->sum('jumlah');
        $tarik = Simpanan::where('user_id', $userId)->where('jenis', $jenis)->where('tipe', 'TARIK')->where('status', 'BERHASIL')->sum('jumlah');
        $pending = Simpanan::where('user_id', $userId)->where('jenis', $jenis)->where('tipe', 'TARIK')->where('status', 'PENDING')->sum('jumlah');

        return (float) ($setor - $tarik - $pending);
    }
}