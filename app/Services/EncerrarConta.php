<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Pedido;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Encerra a conta do cliente: exclusão lógica + anonimização dos dados pessoais (LGPD).
 * Como "apagar o nome da ficha" sem rasgar as notas fiscais: os pedidos ficam, os dados pessoais saem.
 */
class EncerrarConta
{
    // Pedidos que impedem o encerramento
    public const EM_ANDAMENTO = [Pedido::AGUARDANDO_PAGAMENTO, Pedido::PAGO, Pedido::EM_SEPARACAO, Pedido::ENVIADO];

    /**
     * @throws PagamentoException com a mensagem para a tela
     */
    public function encerrar(User $cliente): void
    {
        $andamento = Pedido::where('user_id', $cliente->id)->whereIn('status', self::EM_ANDAMENTO)->orderBy('id')->get();
        if ($andamento->isNotEmpty()) {
            $lista = $andamento->map(fn (Pedido $p) => $p->numero().' ('.$p->statusLabel().')')->implode(', ');

            throw new PagamentoException("Você tem pedido(s) em andamento: {$lista}. Aguarde a entrega ou cancele o pedido antes de encerrar a conta.");
        }

        // Banco: anonimização, status e sessões na MESMA transação (ou tudo acontece, ou nada)
        DB::transaction(function () use ($cliente) {
            $cliente->forceFill([
                'name' => 'Cliente removido',
                // E-mail único e impossível de usar: o original fica livre para uma conta nova
                'email' => "removido-{$cliente->id}@conta-encerrada.invalid",
                'telefone' => null,
                'cpf' => null, // LGPD: o CPF sai junto; o mesmo CPF pode abrir uma conta nova depois
                'asaas_cliente_id' => null, // o vínculo com o cliente no Asaas também sai
                'cep' => null, 'rua' => null, 'numero' => null, 'complemento' => null,
                'bairro' => null, 'cidade' => null, 'uf' => null,
                'password' => Hash::make(Str::random(40)), // ninguém sabe a senha
                'remember_token' => null,
                'status' => User::STATUS_ENCERRADO,
                'encerrada_em' => now(),
            ])->save();

            // Carrinho aberto é esvaziado (os pedidos antigos continuam intactos)
            Carrinho::where('user_id', $cliente->id)->where('status', Carrinho::ABERTO)->get()
                ->each(function (Carrinho $carrinho) {
                    $carrinho->itens()->delete();
                    $carrinho->cupom = null;
                    $carrinho->save();
                });

            // Derruba todas as sessões abertas (outros navegadores/celular)
            DB::table('sessions')->where('user_id', $cliente->id)->delete();

            Auditoria::registrar('encerrou_conta', $cliente);
        });
    }
}
