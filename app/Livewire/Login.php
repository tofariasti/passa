<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.cardholder')]
#[Title('Entrar')]
final class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirect('/my-card');
        }
    }

    public function authenticate(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ]);

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password])) {
            $this->addError('email', 'E-mail ou senha incorretos.');

            return;
        }

        session()->regenerate();

        $this->redirect('/my-card');
    }

    public function render(): View
    {
        return view('livewire.login');
    }
}
