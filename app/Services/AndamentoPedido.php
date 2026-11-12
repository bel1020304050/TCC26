<?php

namespace App\Services;

use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Support\Auditoria;
use App\Support\Rastreio;
use Illuminate\Support\Facades\DB;

/**
 * Andamento do pedido: atualizar status e cancelar.
 * As permissões (quem pode) ficam na PedidoPolicy; aqui ficam as regras do fluxo.
 */
class AndamentoPedido
{
    /**
     * Avança para o próximo status. Regra: só aceita exatamente o próximo passo (sem pular nem voltar).
     *
     * @throws PagamentoException com a mensagem para a tela
     */
    public function atualizarStatus(Pedido $pedido, string $novoStatus, User $responsavel): void
    {
        DB::transaction(function () use ($pedido, $novoStatus, $responsavel) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $proximo = $pedido->proximoStatus();

            if ($pedido->status === Pedido::AGUARDANDO_PAGAMENTO) {
                throw new PagamentoException('"Pago" é definido automaticamente pela confirmação do pagamento.');
            }
            if (! $proximo) {
                throw new PagamentoException("O pedido {$pedido->numero()} está {$pedido->statusLabel()} e não tem próximo status.");
            }
            if ($novoStatus !== $proximo) {
                throw new PagamentoException('Transição não permitida: o próximo status permitido é '.Pedido::STATUS[$proximo].'.');
            }

            $anterior = $pedido->status;
            $observacao = null;

            // Regra: ao passar para "Enviado", o pedido ganha um código de rastreio (simulado, formato dos Correios)
            if ($novoStatus === Pedido::ENVIADO && ! $pedido->codigo_rastreio) {
                $pedido->codigo_rastreio = Rastreio::gerar();
                $observacao = "Código de rastreio (simulado): {$pedido->codigo_rastreio}";
            }

            $pedido->registrarStatus($novoStatus, $responsavel, $observacao);
            Auditoria::registrar('alterou_status', $pedido, array_filter(
                ['de' => $anterior, 'para' => $novoStatus, 'rastreio' => $pedido->codigo_rastreio]), autor: $responsavel);
        });
    }

    /**
     * Cancela o pedido (exclusão lógica). Banco: tudo numa transação:
     * status Cancelado + estorno do pagamento aprovado + devolução ao estoque.
     *
     * @throws PagamentoException
     */
    public function cancelar(Pedido $pedido, User $responsavel, ?string $motivo): void
    {
        DB::transaction(function () use ($pedido, $responsavel, $motivo) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->firstOrFail();
            $anterior = $pedido->status;

            if (in_array($anterior, [Pedido::ENVIADO, Pedido::ENTREGUE, Pedido::CANCELADO], true)) {
                throw new PagamentoException("Pedido {$pedido->statusLabel()} não pode ser cancelado.");
            }

            if ($anterior === Pedido::AGUARDANDO_PAGAMENTO) {
                // PIX ou boleto em aberto deixam de valer; nada foi cobrado nem baixado do estoque
                $pedido->pagamentos()->where('status', Pagamento::PENDENTE)->update([
                    'status' => Pagamento::RECUSADO,
                    'mensagem' => 'Cobrança cancelada junto com o pedido.',
                ]);

                // Regra: os itens voltam para um carrinho editável (ver Carrinho::devolverItensAoCliente)
                $pedido->carrinho?->devolverItensAoCliente();
            } else {
                // Pago ou Em Separação: estorna o pagamento aprovado e devolve as peças ao estoque
                $pedido->pagamentos()->where('status', Pagamento::APROVADO)->get()->each(function (Pagamento $pagamento) use ($pedido, $responsavel) {
                    $pagamento->status = Pagamento::ESTORNADO;
                    $pagamento->mensagem = 'Estornado no cancelamento do pedido.';
                    $pagamento->save();
                    FinalizarCompra::auditarPagamento('pagamento_estornado', $pagamento, $pedido, $responsavel);
                });

                $pedido->load('itens');
                $produtos = Produto::whereIn('id', $pedido->itens->pluck('produto_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($pedido->itens as $item) {
                    $produtos[$item->produto_id]->increment('estoque', $item->quantidade);
                }
            }

            $pedido->registrarStatus(Pedido::CANCELADO, $responsavel, $motivo ? 'Motivo: '.$motivo : null);
            Auditoria::registrar('cancelou', $pedido, ['de' => $anterior], $motivo, $responsavel);
        });
    }
}
