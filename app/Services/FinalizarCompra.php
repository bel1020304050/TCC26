<?php

namespace App\Services;

use App\Models\Carrinho;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Pagamentos\Asaas\ErroAsaas;
use App\Pagamentos\PagamentoException;
use App\Pagamentos\ProvedorIndisponivel;
use App\Pagamentos\ProvedorPagamento;
use App\Pagamentos\RespostaProvedor;
use App\Support\Auditoria;
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
        $substituidos = $pedido->pagamentos()->where('status', Pagamento::PENDENTE)->get();
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

        // A cobrança antiga também é cancelada no provedor (senão o cliente ainda conseguiria pagá-la)
        $substituidos->each(fn (Pagamento $antigo) => $this->provedor->cancelar($antigo));

        // 3/4. Envia ao provedor e grava o retorno
        try {
            $resposta = $this->provedor->cobrar($pagamento, $dadosCartao);
        } catch (ProvedorIndisponivel) {
            $this->recusarTentativa($pagamento, $pedido, 'Falha temporária de comunicação com o provedor.');

            throw new PagamentoException('O serviço de pagamento está temporariamente indisponível. Tente de novo em instantes; o seu pedido continua reservado para pagamento.');
        } catch (ErroAsaas $e) {
            // O Asaas recusou o pedido de cobrança (ex.: CPF inválido, valor abaixo do mínimo)
            $this->recusarTentativa($pagamento, $pedido, 'Asaas: '.$e->getMessage());

            throw new PagamentoException('O Asaas não aceitou a cobrança: '.$e->getMessage());
        } catch (PagamentoException $e) {
            // Falta algo do lado do cliente (ex.: CPF) — a mensagem já é para a tela
            $this->recusarTentativa($pagamento, $pedido, $e->getMessage());

            throw $e;
        }

        $pagamento->referencia = $resposta->referencia;
        $pagamento->mensagem = $resposta->mensagem;
        foreach ($resposta->dados as $coluna => $valor) {
            $pagamento->{$coluna} = $valor;
        }
        $pagamento->status = $resposta->status === Pagamento::APROVADO ? Pagamento::PENDENTE : $resposta->status;
        $pagamento->save();
        if ($pagamento->status === Pagamento::RECUSADO) {
            self::auditarPagamento('pagamento_recusado', $pagamento, $pedido, sistema: true);
        }

        // 5. Aprovado na hora (cartão): finaliza
        if ($resposta->status === Pagamento::APROVADO) {
            $this->confirmarAprovacao($pagamento);
        }

        return $pagamento->fresh();
    }

    /**
     * Consulta automática da tela de pagamento e aviso (webhook): pergunta ao provedor como está a cobrança.
     * Não confia em quem chamou: o status vem sempre da consulta ao provedor.
     *
     * @throws PagamentoException quando o provedor não responde
     */
    public function verificar(Pagamento $pagamento): Pagamento
    {
        if (! $pagamento->isPendente() || ! $pagamento->referencia) {
            return $pagamento;
        }

        try {
            $resposta = $this->provedor->consultar($pagamento);
        } catch (ProvedorIndisponivel|ErroAsaas $e) {
            throw new PagamentoException('Não foi possível consultar o pagamento agora: '.$e->getMessage());
        }

        return $this->aplicarResposta($pagamento, $resposta);
    }

    /**
     * Ambiente de testes: faz de conta que o PIX/boleto foi pago.
     * No simulado aprova direto; no Asaas, pede ao sandbox para confirmar e depois consulta o status.
     *
     * @param  User|null  $responsavel  quem confirmou à mão (ex.: Gerente no boleto); null = sistema
     *
     * @throws PagamentoException
     */
    public function simular(Pagamento $pagamento, ?User $responsavel = null): Pagamento
    {
        if (! $pagamento->isPendente()) {
            return $pagamento;
        }

        try {
            $resposta = $this->provedor->simularPagamento($pagamento);
        } catch (ProvedorIndisponivel|ErroAsaas $e) {
            throw new PagamentoException('Não foi possível simular o pagamento: '.$e->getMessage());
        }

        return $this->aplicarResposta($pagamento, $resposta, $responsavel);
    }

    // Aplica o que o provedor respondeu: aprovado finaliza; recusado/estornado encerra a tentativa; pendente não muda nada
    private function aplicarResposta(Pagamento $pagamento, RespostaProvedor $resposta, ?User $responsavel = null): Pagamento
    {
        if ($resposta->status === Pagamento::APROVADO) {
            if ($resposta->dados) {
                $pagamento->forceFill($resposta->dados)->save(); // bandeira e final do cartão pago na fatura
            }

            return $this->confirmarAprovacao($pagamento, $responsavel);
        }

        if (in_array($resposta->status, [Pagamento::RECUSADO, Pagamento::ESTORNADO], true)) {
            return DB::transaction(function () use ($pagamento, $resposta) {
                $pagamento = Pagamento::whereKey($pagamento->id)->lockForUpdate()->firstOrFail();
                if ($pagamento->isPendente() && ! $pagamento->processado_em) {
                    // Uma cobrança que nunca foi aprovada e voltou como estornada é tratada como recusada
                    $this->recusarTentativa($pagamento, $pagamento->pedido, $resposta->mensagem ?? 'Cobrança encerrada pelo provedor.');
                }

                return $pagamento;
            });
        }

        return $pagamento->fresh();
    }

    private function recusarTentativa(Pagamento $pagamento, Pedido $pedido, string $mensagem): void
    {
        $pagamento->status = Pagamento::RECUSADO;
        $pagamento->mensagem = mb_substr($mensagem, 0, 255);
        $pagamento->save();
        self::auditarPagamento('pagamento_recusado', $pagamento, $pedido, sistema: true);
    }

    /**
     * Etapa 5 — retorno de APROVAÇÃO do provedor (no Asaas, chega pelo aviso/webhook ou pela consulta automática da tela).
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
                self::auditarPagamento('pagamento_estornado', $pagamento, $pedido, sistema: true);

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

                $motivo = 'Cancelado automaticamente: estoque insuficiente de '.$faltando->map->descricao()->implode(', ').'.';
                $pedido->registrarStatus(Pedido::CANCELADO, $responsavel, $motivo);
                $this->reabrirCarrinho($pedido);

                // Auditoria: quem cancelou foi o próprio sistema, não uma pessoa
                self::auditarPagamento('pagamento_estornado', $pagamento, $pedido, sistema: true);
                Auditoria::registrar('cancelou', $pedido, ['de' => Pedido::AGUARDANDO_PAGAMENTO], $motivo, sistema: true);

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

            // Auditoria: boleto confirmado à mão aparece com o nome de quem confirmou; o resto, como "Sistema"
            self::auditarPagamento('pagamento_aprovado', $pagamento, $pedido, $responsavel, sistema: ! $responsavel);

            if ($pedido->carrinho) {
                $pedido->carrinho->itens()->delete(); // carrinho "limpo"
                $pedido->carrinho->status = Carrinho::FINALIZADO;
                $pedido->carrinho->save();
            }

            return $pagamento;
        });
    }

    // Registra na auditoria um evento de pagamento, ligado ao pedido (é o que a equipe procura no relatório)
    public static function auditarPagamento(string $acao, Pagamento $pagamento, Pedido $pedido, ?User $autor = null, bool $sistema = false): void
    {
        Auditoria::registrar($acao, $pedido, [
            'forma' => $pagamento->forma,
            'valor' => (float) $pagamento->valor,
            'mensagem' => $pagamento->mensagem,
            'manual' => $autor !== null,
        ], autor: $autor, sistema: $sistema);
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

    // Regra: pedido cancelado antes do pagamento devolve os itens para um carrinho editável
    private function reabrirCarrinho(Pedido $pedido): void
    {
        $pedido->carrinho?->devolverItensAoCliente();
    }
}
