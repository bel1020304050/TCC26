<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Http\Requests\EditarContaRequest;
use App\Pagamentos\PagamentoException;
use App\Services\EncerrarConta;
use App\Support\Auditoria;
use App\Support\Email;
use App\Support\Sessoes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * "Minha conta" do cliente: ver, editar os dados, trocar a senha e encerrar a conta.
 * (O cadastro fica em Auth\RegisterController.)
 * Segurança: nenhuma rota recebe id; tudo usa o usuário logado, então ninguém acessa a conta de outro.
 */
class ContaController extends Controller
{
    private const CAMPOS = ['name', 'email', 'cpf', 'telefone', 'cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'];

    // LGPD: a auditoria registra só QUAIS informações o cliente mudou, nunca os valores (nome, e-mail, CPF, endereço)
    private const ROTULOS_AUDITORIA = [
        'name' => 'Nome', 'email' => 'E-mail', 'cpf' => 'CPF', 'telefone' => 'Telefone', 'cep' => 'Endereço', 'rua' => 'Endereço',
        'numero' => 'Endereço', 'complemento' => 'Endereço', 'bairro' => 'Endereço', 'cidade' => 'Endereço', 'uf' => 'Endereço',
    ];

    // Tela "Meus dados"
    public function show(Request $request): View
    {
        return view('loja.conta.show', [
            'cliente' => $request->user(),
            'emAndamento' => \App\Models\Pedido::where('user_id', $request->user()->id)
                ->whereIn('status', EncerrarConta::EM_ANDAMENTO)->count(),
        ]);
    }

    // Formulário de edição (dados pessoais, endereço e senha)
    public function edit(Request $request): View
    {
        return view('loja.conta.edit', ['cliente' => $request->user()]);
    }

    public function update(EditarContaRequest $request): RedirectResponse
    {
        $cliente = $request->user();
        $cliente->fill($request->safe()->only(self::CAMPOS)); // perfil/status nunca vêm do formulário
        $alterados = array_values(array_unique(array_map(fn ($campo) => self::ROTULOS_AUDITORIA[$campo], array_keys($cliente->getDirty()))));
        $trocouEmail = $cliente->isDirty('email');
        if ($trocouEmail) {
            // Regra: e-mail novo precisa ser confirmado de novo antes da próxima compra
            $cliente->email_verified_at = null;
        }
        $cliente->save();
        $enviou = ! $trocouEmail || Email::enviar(fn () => $cliente->sendEmailVerificationNotification());
        if ($alterados) {
            Auditoria::registrar('editou', $cliente, ['campos' => $alterados]);
        }

        // Pedidos já criados guardam a própria cópia do endereço: não mudam
        $mensagem = 'Seus dados foram atualizados. Os próximos pedidos usarão este endereço.';
        if ($trocouEmail) {
            $mensagem .= $enviou ? " Enviamos um link para confirmar o novo e-mail ({$cliente->email})." : ' '.Email::AVISO_FALHA;
        }

        return redirect()->route('loja.conta')->with('sucesso', $mensagem);
    }

    public function senha(Request $request): RedirectResponse
    {
        $request->validateWithBag('senha', [
            'senha_atual' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'senha_atual.current_password' => 'A senha atual está incorreta.',
        ], [
            'senha_atual' => 'senha atual', 'password' => 'nova senha',
        ]);

        $request->user()->update(['password' => $request->input('password')]);
        Sessoes::encerrarOutras($request->user(), $request);
        Auditoria::registrar('alterou_senha', $request->user());

        return redirect()->route('loja.conta')->with('sucesso', 'Senha alterada com sucesso. Os outros aparelhos conectados foram desconectados.');
    }

    // Encerrar a conta. Segurança: exige a senha atual, para ninguém encerrar a conta de outra pessoa num computador aberto
    public function destroy(Request $request, EncerrarConta $encerrar): RedirectResponse
    {
        $request->validateWithBag('encerrar', [
            'senha_encerrar' => ['required', 'current_password'],
        ], [
            'senha_encerrar.required' => 'Informe a sua senha atual para confirmar.',
            'senha_encerrar.current_password' => 'A senha atual está incorreta.',
        ]);

        try {
            $encerrar->encerrar($request->user());
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('loja.inicio')
            ->with('sucesso', 'Sua conta foi encerrada e seus dados pessoais foram removidos. Obrigada por ter comprado com a Mary Modas.');
    }
}
