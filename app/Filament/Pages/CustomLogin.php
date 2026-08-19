<?php

namespace App\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class CustomLogin extends Login
{
    protected int $maxLoginAttempts = 5;

    protected int $loginDecaySeconds = 60;

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

    protected function throttleKey(string $nip): string
    {
        return 'super-admin-login|' . Str::lower($nip) . '|' . request()->ip();
    }

    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();

        $throttleKey = $this->throttleKey($data['nip'] ?? '');

        if (RateLimiter::tooManyAttempts($throttleKey, $this->maxLoginAttempts)) {
            $detikTersisa = RateLimiter::availableIn($throttleKey);

            $this->data['password'] = null;
            $this->form->fill(['password' => null]);

            Notification::make()
                ->title('Terlalu banyak percobaan login')
                ->body("Login untuk NIP ini diblokir sementara karena terlalu banyak percobaan gagal. Coba lagi dalam {$detikTersisa} detik.")
                ->danger()
                ->send();

            return null;
        }

        if (!auth()->attempt([
            'nip' => $data['nip'],
            'password' => $data['password'],
        ])) {
            RateLimiter::hit($throttleKey, $this->loginDecaySeconds);

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

            RateLimiter::hit($throttleKey, $this->loginDecaySeconds);

            Notification::make()
                ->title('Akses ditolak')
                ->body('Anda tidak memiliki akses ke panel ini.')
                ->danger()
                ->send();

            return null;
        }

        RateLimiter::clear($throttleKey);

        session()->regenerate();

        return app(LoginResponse::class);
    }
}
