<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Minha conta" da equipe (seção 2.2): Administrador e Funcionário alteram
 * apenas nome, telefone e senha. Perfil e status não aparecem aqui.
 */
class MinhaContaController extends Controller
{
    public function edit(Request $request): View
    {
        return view('painel.minha-conta', ['usuario' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'telefone' => ['nullable', 'string', 'max:20'],
        ]);

        // Sempre o usuário da sessão, nunca um id enviado pelo navegador
        $request->user()->update($dados);

        return back()->with('sucesso', 'Seus dados foram atualizados.');
    }

    public function senha(Request $request): RedirectResponse
    {
        $request->validateWithBag('senha', [
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'senha_atual.current_password' => 'A senha atual está incorreta.',
        ], [
            'senha_atual' => 'senha atual',
            'password' => 'nova senha',
        ]);

        $usuario = $request->user();
        $usuario->password = $request->input('password');
        $usuario->save();

        Auditoria::registrar('alterou_senha', $usuario);

        return back()->with('sucesso', 'Senha alterada com sucesso.');
    }
}
