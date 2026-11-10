<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Troca obrigatória da senha provisória (equipe).
 * Regra: quem entrou com uma senha gerada pelo Administrador ou pelo Gerente só usa o sistema
 * depois de criar a própria senha (middleware ExigeTrocaDeSenha). Assim, nem quem gerou fica sabendo a senha final.
 */
class SenhaProvisoriaController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->senha_provisoria) {
            return redirect()->route($request->user()->homeRoute());
        }

        return view('auth.senha-provisoria');
    }

    public function update(Request $request): RedirectResponse
    {
        $usuario = $request->user();

        $request->validate([
            'password' => [
                'required', 'confirmed', Password::min(8),
                // Regra: a nova senha não pode ser a mesma senha provisória
                function (string $attribute, mixed $value, Closure $fail) use ($usuario) {
                    if (Hash::check($value, $usuario->password)) {
                        $fail('Crie uma senha diferente da senha provisória.');
                    }
                },
            ],
        ], [], ['password' => 'nova senha']);

        $usuario->password = $request->input('password');
        $usuario->senha_provisoria = false; // não é "fillable": muda só por aqui
        $usuario->save();

        $request->session()->regenerate();
        Auditoria::registrar('trocou_senha_provisoria', $usuario);

        return redirect()->route($usuario->homeRoute())->with('sucesso', 'Senha criada com sucesso. Bem-vinda(o)!');
    }
}
