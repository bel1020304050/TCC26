<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Pagamentos\ProvedorIndisponivel;
use App\Pagamentos\ProvedorPagamento;
use Illuminate\Support\Facades\DB;

/**
 * Processo de Finalizar Compra / Pagamento.
 *
 * Etapas:
 *  1. Validar que o pedido é do cliente e está Aguardando Pagamento
 *  2. Revalidar o estoque dos itens
 *  3. Registrar a tentativa como Pendente e enviar ao provedor
 *  4. Receber o retorno: Aprovado, Recusado ou Pendente
 *  5. Se Aprovado, numa ÚNICA transação: pedido Pago, estoque baixado, carrinho Finalizado e limpo
 *  6. Exibir o resultado (feito pelo controller)
 */
class FinalizarCompra
{
    public function __construct(private readonly ProvedorPagamento $provedor) {}

    /**
     * Etapas 1 a 5: cria a tentativa e envia ao provedor.
     *
     * @param  array<string, string>  $dadosCartao  usados só durante a chamada; nunca são gravados
     *
     * @throws PagamentoException
     */
    public function pagar(Pedido $pedido, User $cliente, string $forma, array $dadosCartao = []): Pagamento
    {
        // 1. Dono do pedido e status
        if ($pedido->user_id !== $cliente->id) {
            throw new PagamentoException('Este pedido não pertence a você.');
        }
        if ($pedido->status !== Pedido::AGUARDANDO_PAGAMENTO) {
            throw new PagamentoException("O pedido {$pedido->numero()} não está aguardando pagamento.");
        }

        // 2. Estoque ainda cabe? (sem baixar nada ainda)
        if ($faltando = $this->itensSemEstoque($pedido)) {
            throw new PagamentoException('Estoque insuficiente para: '.implode('; ', $faltando)
                .'. Cancele o pedido e ajuste o carrinho.');
        }

        // 3. Registra a tentativa como Pendente. Um PIX/boleto anterior ainda pendente deixa de valer.
        $pagamento = DB::transaction(function () use ($pedido, $forma) {
            $pedido->pagamentos()->where('status', Pagamento::PENDENTE)->update([
                'status' => Pagamento::RECUSADO,
                'mensagem' => 'Substituído por uma nova tentativa de pagamento.',
            ]);

            $pagamento = new Pagamento;
            $pagamento->pedido_id = $pedido->id;
            $pagamento->forma = $forma;
            $pagamento->valor = $pedido->total; // cobra o valor guardado no pedido, não o preço atual
            $pagamento->save();

            return $pagamento;
        });

        // 3/4. Envia ao provedor e grava o retorno
        try {
            $resposta = $this->provedor->cobrar($pagamento, $dadosCartao);
        } catch (ProvedorIndisponivel) {
            $pagamento->status = Pagamento::RECUSADO;
            $pagamento->mensagem = 'Falha temporária de comunicação com o provedor.';
            $pagamento->save();

            throw new PagamentoException('O serviço de pagamento está temporariamente indisponível. Tente de novo em instantes; o seu pedido continua reservado para pagamento.');
        }

        $pagamento->referencia = $resposta->referencia;
        $pagamento->mensagem = $resposta->mensagem;
        foreach ($resposta->dados as $coluna => $valor) {
            $pagamento->{$coluna} = $valor;
        }
        $pagamento->status = $resposta->status === Pagamento::APROVADO ? Pagamento::PENDENTE : $resposta->status;
        $pagamento->save();

        // 5. Aprovado na hora (cartão): finaliza
        if ($resposta->status === Pagamento::APROVADO) {
            $this->confirmarAprovacao($pagamento);
        }

        return $pagamento->fresh();
    }

    /**
     * Etapa 5 — retorno de APROVAÇÃO do provedor (no Mercado Pago, viria por webhook).
     * Pode ser chamado mais de uma vez para o mesmo pagamento: só a primeira tem efeito
     * (idempotência — o estoque nunca é baixado duas vezes).
     *
     * @param  User|null  $responsavel  quem confirmou manualmente (ex.: Administrador no boleto); null = sistema
     */
    public function confirmarAprovacao(Pagamento $pagamento, ?User $responsavel = null): Pagamento
    {
        return DB::transaction(function () use ($pagamento, $responsavel) {
            // Trava as linhas: duas confirmações ao mesmo tempo viram uma fila
            $pagamento = Pagamento::whereKey($pagamento->id)->lockForUpdate()->firstOrFail();
            $pedido = Pedido::whereKey($pagamento->pedido_id)->lockForUpdate()->firstOrFail();

            // Idempotência: aprovação já processada (ou tentativa encerrada) não faz nada de novo
            if ($pagamento->processado_em || ! $pagamento->isPendente()) {
                return $pagamento;
            }

            $pagamento->processado_em = now();
            $pagamento->confirmado_por = $responsavel?->id;

            // Pedido já não aguarda pagamento (ex.: cancelado antes): o valor é devolvido
            if ($pedido->status !== Pedido::AGUARDANDO_PAGAMENTO) {
                $pagamento->status = Pagamento::ESTORNADO;
                $pagamento->mensagem = 'Pedido não estava mais aguardando pagamento. Valor estornado.';
                $pagamento->save();

                return $pagamento;
            }

            // Revalida e TRAVA o estoque de cada produto (ordem por id evita travamento cruzado)
            $pedido->load('itens');
            $produtos = Produto::whereIn('id', $pedido->itens->pluck('produto_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $faltando = $pedido->itens->filter(fn ($item) => $produtos[$item->produto_id]->estoque < $item->quantidade);

            if ($faltando->isNotEmpty()) {
                // Regra: se faltar estoque na hora da aprovação, o pedido é Cancelado e o pagamento Estornado
                $pagamento->status = Pagamento::ESTORNADO;
                $pagamento->mensagem = 'Estoque insuficiente no momento da aprovação. Valor estornado.';
                $pagamento->save();

                $pedido->registrarStatus(Pedido::CANCELADO, $responsavel,
                    'Cancelado automaticamente: estoque insuficiente de '.$faltando->map->descricao()->implode(', ').'.');
                $this->reabrirCarrinho($pedido);

                return $pagamento;
            }

            // Tudo certo: baixa o estoque, marca Pago e finaliza o carrinho
            foreach ($pedido->itens as $item) {
                $produtos[$item->produto_id]->decrement('estoque', $item->quantidade);
            }

            $pagamento->status = Pagamento::APROVADO;
            $pagamento->mensagem = 'Pagamento aprovado.';
            $pagamento->save();

            $pedido->forma_pagamento = $pagamento->forma;
            $pedido->registrarStatus(Pedido::PAGO, $responsavel,
                $responsavel ? 'Compensação do boleto confirmada manualmente.' : null);

            if ($pedido->carrinho) {
                $pedido->carrinho->itens()->delete(); // carrinho "limpo"
                $pedido->carrinho->status = Carrinho::FINALIZADO;
                $pedido->carrinho->save();
            }

            return $pagamento;
        });
    }

    // Itens do pedido cujo estoque atual não cobre a quantidade comprada
    private function itensSemEstoque(Pedido $pedido): array
    {
        $pedido->load('itens.produto');

        return $pedido->itens
            ->filter(fn ($item) => $item->produto->estoque < $item->quantidade)
            ->map(fn ($item) => "{$item->descricao()} (disponível: {$item->produto->estoque})")
            ->values()->all();
    }

    // Regra: pedido cancelado antes do pagamento devolve o carrinho para "Aberto" (editável)
    private function reabrirCarrinho(Pedido $pedido): void
    {
        if ($pedido->carrinho && $pedido->carrinho->status === Carrinho::EM_PAGAMENTO) {
            $pedido->carrinho->status = Carrinho::ABERTO;
            $pedido->carrinho->save();
        }
    }
}
