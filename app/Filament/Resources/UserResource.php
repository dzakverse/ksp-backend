<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Manajemen Pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Data Utama Pengguna')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('nama')
                                ->label('Nama Lengkap')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('nip')
                                ->label('NIP / Nomor Induk')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(20)
                                ->validationMessages(['unique' => 'NIP sudah terdaftar']),

                            TextInput::make('nik')
                                ->label('NIK')
                                ->maxLength(16)
                                ->numeric()
                                ->unique(ignoreRecord: true)
                                ->validationMessages(['unique' => 'NIK sudah terdaftar']),

                            Select::make('jenis_kelamin')
                                ->label('Jenis Kelamin')
                                ->options([
                                    'Laki-Laki' => 'Laki-Laki',
                                    'Perempuan' => 'Perempuan',
                                ]),

                            TextInput::make('tempat_lahir')
                                ->label('Tempat Lahir')
                                ->maxLength(255),

                            DatePicker::make('tanggal_lahir')
                                ->label('Tanggal Lahir')
                                ->maxDate(now())
                                ->displayFormat('d/m/Y'),

                            TextInput::make('unit_kerja')
                                ->label('Unit Kerja')
                                ->maxLength(255),

                            Select::make('role')
                                ->label('Role Akses')
                                ->options([
                                    'SUPER_ADMIN' => 'Super Admin',
                                    'KETUA' => 'Ketua',
                                    'BENDAHARA' => 'Bendahara',
                                    'ANGGOTA' => 'Anggota',
                                ])
                                ->required()
                                ->default('ANGGOTA'),

                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),

                            TextInput::make('whatsapp')
                                ->label('No. WhatsApp')
                                ->tel()
                                ->maxLength(20),

                            DatePicker::make('tanggal_bergabung')
                                ->label('Tanggal Bergabung')
                                ->default(now()),

                            Select::make('status_keanggotaan')
                                ->label('Status Keanggotaan')
                                ->options([
                                    'AKTIF' => 'Aktif',
                                    'NONAKTIF' => 'Nonaktif',
                                ])
                                ->default('AKTIF')
                                ->required()
                                ->helperText('Akun berstatus Nonaktif tidak dapat login ke sistem.'),
                        ]),

                        Textarea::make('alamat')
                            ->label('Alamat Lengkap')
                            ->rows(3),
                    ])->columns(1),

                Section::make('Keamanan Akun')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn (string $state): string => bcrypt($state)),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nip')
                    ->label('NIP')
                    ->searchable()
                    ->sortable(),

                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'danger' => 'SUPER_ADMIN',
                        'warning' => 'KETUA',
                        'info' => 'BENDAHARA',
                        'success' => 'ANGGOTA',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SUPER_ADMIN' => 'Super Admin',
                        'KETUA' => 'Ketua',
                        'BENDAHARA' => 'Bendahara',
                        'ANGGOTA' => 'Anggota',
                        default => $state,
                    }),

                TextColumn::make('email')
                    ->label('Email')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('whatsapp')
                    ->label('WhatsApp')
                    ->placeholder('-'),

                BadgeColumn::make('status_keanggotaan')
                    ->label('Status')
                    ->colors([
                        'success' => 'AKTIF',
                        'danger' => 'NONAKTIF',
                    ])
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'NONAKTIF' => 'Nonaktif',
                        default => 'Aktif',
                    }),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'SUPER_ADMIN' => 'Super Admin',
                        'KETUA' => 'Ketua',
                        'BENDAHARA' => 'Bendahara',
                        'ANGGOTA' => 'Anggota',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),

                // FITUR GANTI PASSWORD DIRECT BY SUPER ADMIN
                Tables\Actions\Action::make('changePassword')
                    ->label('Ganti Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading('Ganti Password User')
                    ->modalSubmitActionLabel('Simpan Password Baru')
                    ->form(fn (User $record): array => [
                        TextInput::make('new_password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6),

                        TextInput::make('new_password_confirmation')
                            ->label('Konfirmasi Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('new_password'),
                    ])
                    ->action(function (User $record, array $data): void {
                        $record->update([
                            'password' => bcrypt($data['new_password']),
                        ]);

                        Notification::make()
                            ->title('Password berhasil diubah')
                            ->body("Password untuk {$record->nama} telah diperbarui.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),

                // FITUR AKTIF/NONAKTIFKAN AKUN (mis. Super Admin menonaktifkan/mengaktifkan Bendahara)
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn (User $record): string => $record->status_keanggotaan === 'NONAKTIF' ? 'Aktifkan' : 'Nonaktifkan')
                    ->icon(fn (User $record): string => $record->status_keanggotaan === 'NONAKTIF' ? 'heroicon-o-check-circle' : 'heroicon-o-no-symbol')
                    ->color(fn (User $record): string => $record->status_keanggotaan === 'NONAKTIF' ? 'success' : 'danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => $record->status_keanggotaan === 'NONAKTIF'
                        ? "Aktifkan akun {$record->nama}?"
                        : "Nonaktifkan akun {$record->nama}?")
                    ->modalDescription('Akun yang dinonaktifkan tidak akan bisa login ke sistem hingga diaktifkan kembali.')
                    ->action(function (User $record): void {
                        $statusBaru = $record->status_keanggotaan === 'NONAKTIF' ? 'AKTIF' : 'NONAKTIF';
                        $record->update(['status_keanggotaan' => $statusBaru]);

                        Notification::make()
                            ->title($statusBaru === 'AKTIF' ? 'Akun berhasil diaktifkan' : 'Akun berhasil dinonaktifkan')
                            ->body("{$record->nama} sekarang berstatus " . ($statusBaru === 'AKTIF' ? 'Aktif' : 'Nonaktif') . '.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => $record->id !== auth()->id()),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}