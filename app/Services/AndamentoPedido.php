<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;

/**
 * Andamento do pedido — stories 3.20 (atualizar status) e 3.21 (cancelar).
 * As permissões (quem pode) ficam na PedidoPolicy; aqui ficam as regras do fluxo.
 */
class AndamentoPedido
{
    /**
     * 3.20 — Avança para o próximo status. Só aceita exatamente o próximo passo (sem saltos nem retornos).
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
            $pedido->registrarStatus($novoStatus, $responsavel);
            Auditoria::registrar('alterou_status', $pedido, ['de' => $anterior, 'para' => $novoStatus]);
        });
    }

    /**
     * 3.21 — Cancela o pedido (exclusão lógica). Tudo numa transação (RNF.01):
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

                // O carrinho volta a ficar editável (3.21 e 3.30)
                if ($pedido->carrinho && $pedido->carrinho->status === Carrinho::EM_PAGAMENTO) {
                    $pedido->carrinho->status = Carrinho::ABERTO;
                    $pedido->carrinho->save();
                }
            } else {
                // Pago ou Em Separação: estorna o pagamento aprovado e devolve as peças ao estoque
                $pedido->pagamentos()->where('status', Pagamento::APROVADO)->get()->each(function (Pagamento $pagamento) {
                    $pagamento->status = Pagamento::ESTORNADO;
                    $pagamento->mensagem = 'Estornado no cancelamento do pedido.';
                    $pagamento->save();
                });

                $pedido->load('itens');
                $produtos = Produto::whereIn('id', $pedido->itens->pluck('produto_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                foreach ($pedido->itens as $item) {
                    $produtos[$item->produto_id]->increment('estoque', $item->quantidade);
                }
            }

            $pedido->registrarStatus(Pedido::CANCELADO, $responsavel, $motivo ? 'Motivo: '.$motivo : null);
            Auditoria::registrar('cancelou', $pedido, ['de' => $anterior, 'motivo' => $motivo]);
        });
    }
}
