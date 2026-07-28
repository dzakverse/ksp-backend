<?php

namespace App\Filament\Actions;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Component;

class ChangePasswordAction implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    public User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Ganti Password')
                    ->description("Mengubah password untuk {$this->user->name}")
                    ->schema([
                        TextInput::make('new_password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->dehydrateStateUsing(fn (string $state): string => bcrypt($state)),
                        TextInput::make('new_password_confirmation')
                            ->label('Konfirmasi Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('new_password'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->user->update([
            'password' => $data['new_password'],
        ]);

        $this->form->fill([]);

        Notification::make()
            ->title('Password berhasil diubah')
            ->success()
            ->send();
    }
}
