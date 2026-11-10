<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CriarContaRequest;
use App\Models\User;
use App\Support\Email;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Cadastro público de clientes ("Criar conta").
 * Sempre cria o perfil Cliente; já deixa a pessoa logada no fim e envia o e-mail de confirmação.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register', ['ufs' => User::UFS]);
    }

    public function store(CriarContaRequest $request): RedirectResponse
    {
        // validated() só traz os campos das regras: um "perfil" enviado pelo navegador é descartado
        $usuario = new User($request->validated());
        $usuario->perfil = User::PERFIL_CLIENTE; // o cadastro público nunca cria equipe
        $usuario->status = User::STATUS_ATIVO;

        try {
            $usuario->save();
        } catch (UniqueConstraintViolationException) {
            // Duplo clique em "Criar conta": as duas requisições passam pela validação ao mesmo tempo,
            // o banco aceita a primeira e recusa a segunda (e-mail/CPF únicos). Mostramos a mensagem normal.
            return back()->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => 'Este e-mail (ou CPF) já está cadastrado. Se acabou de criar a conta, é só entrar.']);
        }

        // Regra: o cliente já pode navegar e montar o carrinho; para comprar, confirma o e-mail pelo link
        $enviou = Email::enviar(fn () => $usuario->sendEmailVerificationNotification());

        Auth::login($usuario);
        $request->session()->regenerate();

        $mensagem = $enviou
            ? "Conta criada com sucesso! Enviamos um link de confirmação para {$usuario->email}: confirme o e-mail para poder finalizar compras."
            : 'Conta criada com sucesso! '.Email::AVISO_FALHA.' (no carrinho ou em Minha conta)';

        return redirect()->route('loja.inicio')->with('sucesso', $mensagem);
    }
}
