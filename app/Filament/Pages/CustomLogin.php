<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login;

class CustomLogin extends Login
{
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nip')
                    ->label('NIP')
                    ->required()
                    ->autocomplete()
                    ->autofocus(),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->required()
                    ->autocomplete(),
            ]);
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        if (!auth()->attempt([
            'nip' => $data['nip'],
            'password' => $data['password'],
        ])) {
            $this->data['password'] = null;

            $this->form->fill(['password' => null]);

            Notification::make()
                ->title('Login gagal')
                ->body('NIP atau password salah.')
                ->danger()
                ->send();

            return null;
        }

        $user = auth()->user();

        if ($user->role !== 'SUPER_ADMIN') {
            auth()->logout();

            Notification::make()
                ->title('Akses ditolak')
                ->body('Anda tidak memiliki akses ke panel ini.')
                ->danger()
                ->send();

            return null;
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
