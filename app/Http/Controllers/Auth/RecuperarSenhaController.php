<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auditoria;
use App\Support\Email;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as RegraSenha;
use Illuminate\View\View;

/**
 * "Esqueci minha senha" do cliente: pede o link por e-mail e cria a nova senha.
 * Regra: só vale para clientes ativos. A equipe não usa este caminho: quem esquece a senha
 * pede uma senha provisória ao Administrador ou ao Gerente.
 */
class RecuperarSenhaController extends Controller
{
    // Mensagem igual para qualquer e-mail. Segurança: não revela quem tem conta na loja.
    public const MENSAGEM_ENVIO = 'Se o e-mail estiver cadastrado, enviaremos um link para criar uma nova senha. Confira também a caixa de spam.';

    public function create(): View
    {
        return view('auth.esqueci-senha');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // O Laravel gera o código (token), guarda em password_reset_tokens e manda o e-mail.
        // Os campos extras filtram a conta: só cliente com status ativo recebe o link.
        $enviou = Email::enviar(fn () => Password::sendResetLink([
            'email' => Str::lower(trim($request->input('email'))),
            'perfil' => User::PERFIL_CLIENTE,
            'status' => User::STATUS_ATIVO,
        ]));

        // Se o servidor de e-mail falhou, avisa (sem revelar se a conta existe)
        if (! $enviou) {
            return back()->withInput()->with('erro', 'Não conseguimos enviar e-mails agora. Tente de novo daqui a pouco.');
        }

        return back()->with('sucesso', self::MENSAGEM_ENVIO);
    }

    // Tela aberta pelo link do e-mail
    public function edit(Request $request, string $token): View
    {
        return view('auth.redefinir-senha', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', RegraSenha::min(8)],
        ], [], ['password' => 'nova senha']);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($request->input('email'))),
                'perfil' => User::PERFIL_CLIENTE,
                'status' => User::STATUS_ATIVO,
                'password' => $request->input('password'),
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $request->input('token'),
            ],
            function (User $cliente, string $senha) {
                $cliente->password = $senha;
                $cliente->setRememberToken(Str::random(60));
                $cliente->save();

                // Segurança: derruba as sessões abertas com a senha antiga (outro computador ou celular)
                DB::table('sessions')->where('user_id', $cliente->id)->delete();
                Auditoria::registrar('recuperou_senha', $cliente);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Link vencido, já usado ou de outro e-mail: a mesma mensagem para todos os casos
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Este link é inválido ou já expirou. Peça um novo em "Esqueci minha senha".']);
        }

        return redirect()->route('login')->with('sucesso', 'Senha alterada! Entre com a sua nova senha.');
    }
}
