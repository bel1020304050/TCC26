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
 * Login e logout. A mesma tela serve para clientes e para a equipe;
 * depois de entrar, cada perfil vai para a sua página inicial.
 */
class LoginController extends Controller
{
    // Segurança: 5 senhas erradas em 1 minuto bloqueiam novas tentativas por 1 minuto (contra quem tenta adivinhar senhas)
    private const MAX_TENTATIVAS = 5;
    private const BLOQUEIO_SEGUNDOS = 60;

    public function create(Request $request): View
    {
        // "Entrar para comprar" na página do produto: depois do login, volta para o mesmo produto.
        // Segurança: só aceita caminho do próprio site ("/produtos/5"), nunca outro endereço ("//site.com", "https://...")
        $voltar = (string) $request->query('voltar');
        if (preg_match('#^/(?![/\\\\])#', $voltar)) {
            $request->session()->put('url.intended', url($voltar));
        }

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
        // Segurança: mensagem genérica, para não revelar se o e-mail está cadastrado
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
