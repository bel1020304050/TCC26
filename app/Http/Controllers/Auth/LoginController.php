<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Story 3.13 – Login e Logout (único para os 3 perfis).
 */
class LoginController extends Controller
{
    // RNF.01 da 3.13: 5 tentativas erradas em 1 minuto bloqueiam por 1 minuto
    private const MAX_TENTATIVAS = 5;
    private const BLOQUEIO_SEGUNDOS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $chave = Str::lower($dados['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, self::MAX_TENTATIVAS)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($chave)]),
            ]);
        }

        // Só entra quem está com status "ativo": conta inativa ou encerrada recebe a mesma
        // mensagem genérica, sem revelar se o e-mail existe (regra da 3.13)
        $credenciais = [
            'email' => Str::lower($dados['email']),
            'password' => $dados['password'],
            'status' => User::STATUS_ATIVO,
        ];

        if (! Auth::attempt($credenciais)) {
            RateLimiter::hit($chave, self::BLOQUEIO_SEGUNDOS);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate(); // evita sequestro de sessão (session fixation)

        // Cada perfil vai para a sua área
        return redirect()->intended(route($request->user()->homeRoute()));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('loja.inicio')->with('sucesso', 'Você saiu da sua conta.');
    }
}
