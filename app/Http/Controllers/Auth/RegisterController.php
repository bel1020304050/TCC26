<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CriarContaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Story 3.9 – Criar Conta (Create do CRUD de Clientes).
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
        $usuario->save();

        Auth::login($usuario);
        $request->session()->regenerate();

        return redirect()->route('loja.inicio')
            ->with('sucesso', 'Conta criada com sucesso! Bem-vinda(o) à Mary Modas.');
    }
}
