<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Carrinho do cliente. Pense numa cesta de supermercado:
 * enquanto está "Aberto" o cliente coloca e tira itens; ao criar o pedido ele vai para o caixa
 * ("Em Pagamento", bloqueado) e, depois do pagamento aprovado, é "Finalizado".
 */
class Carrinho extends Model
{
    public const ABERTO = 'aberto';
    public const EM_PAGAMENTO = 'em_pagamento';
    public const FINALIZADO = 'finalizado';

    protected $fillable = ['user_id'];

    // Promoções candidatas já carregadas (evita repetir a consulta na mesma página)
    private ?\Illuminate\Support\Collection $promocoesCache = null;

    protected $attributes = ['status' => self::ABERTO];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(CarrinhoItem::class);
    }

    public function pedido(): HasOne
    {
        return $this->hasOne(Pedido::class);
    }

    public function isAberto(): bool
    {
        return $this->status === self::ABERTO;
    }

    /**
     * Carrinho atual do cliente (o que ainda não foi finalizado). Cria um novo se não houver.
     */
    public static function doCliente(User $cliente): self
    {
        $atual = fn () => static::where('user_id', $cliente->id)->where('status', '!=', self::FINALIZADO)->latest('id')->first();

        return $atual() ?? DB::transaction(function () use ($cliente, $atual) {
            // Banco: trava a linha do cliente até o fim da transação. Se dois cliques chegam juntos,
            // o segundo espera o primeiro terminar e encontra o carrinho já criado (nunca dois carrinhos abertos).
            static::travarCliente($cliente);

            return $atual() ?? static::create(['user_id' => $cliente->id]);
        });
    }

    // Fila por cliente: enquanto a transação atual não terminar, outro pedido do mesmo cliente espera
    public static function travarCliente(User $cliente): void
    {
        User::whereKey($cliente->id)->lockForUpdate()->first();
    }

    /**
     * Regra: quando um pedido é cancelado antes do pagamento, o cliente recebe os itens de volta num carrinho editável.
     *
     * Banco: cada pedido aponta para o carrinho que o gerou, e a coluna pedidos.carrinho_id é única
     * (um carrinho gera no máximo um pedido). Por isso NÃO reabrimos o mesmo carrinho: se ele voltasse
     * a ser "aberto", o próximo pedido tentaria usar o mesmo carrinho_id e o banco recusaria.
     * Em vez disso, o carrinho antigo fica encerrado junto do pedido cancelado, e os itens
     * (e o cupom) são copiados para um carrinho novo.
     */
    public function devolverItensAoCliente(): ?self
    {
        if ($this->status !== self::EM_PAGAMENTO) {
            return null;
        }

        $novo = static::create(['user_id' => $this->user_id]);
        $novo->cupom = $this->cupom;
        $novo->save();

        foreach ($this->itens as $item) {
            $novo->itens()->create(['produto_id' => $item->produto_id, 'quantidade' => $item->quantidade]);
        }

        $this->status = self::FINALIZADO;
        $this->save();

        return $novo;
    }

    // Quantidade total de peças (para o "Carrinho (n)" do topo)
    public static function quantidadeDoCliente(User $cliente): int
    {
        return (int) CarrinhoItem::whereHas('carrinho', fn ($q) => $q
            ->where('user_id', $cliente->id)->where('status', '!=', self::FINALIZADO))
            ->sum('quantidade');
    }

    /**
     * Segurança: os totais são calculados SEMPRE no servidor, com os preços e promoções atuais
     * (nunca com valores vindos do navegador, que poderiam ser alterados).
     * Regra: descontos NÃO se somam; entre as promoções automáticas e o cupom, vale só o maior.
     *
     * @return array{subtotal: float, desconto: float, total: float, promocao: ?Promocao}
     */
    public function totais(): array
    {
        $this->loadMissing('itens.produto');

        $subtotal = Dinheiro::arredondar($this->itens->sum(fn (CarrinhoItem $item) => $item->subtotal()));

        $melhor = null;
        $desconto = 0.0;
        foreach ($this->promocoesCandidatas() as $promocao) {
            // Soma só os itens que a promoção alcança (Geral, Produto ou Categoria)
            $elegivel = $this->itens->filter(fn (CarrinhoItem $item) => $promocao->aplicaA($item->produto))
                ->sum(fn (CarrinhoItem $item) => $item->subtotal());
            $valor = $promocao->descontoSobre($elegivel);

            if ($valor > $desconto) {
                [$melhor, $desconto] = [$promocao, $valor];
            }
        }

        return [
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'total' => Dinheiro::arredondar(max(0, $subtotal - $desconto)),
            'promocao' => $melhor,
        ];
    }

    // Automáticas válidas + o cupom do carrinho (se ainda for válido)
    private function promocoesCandidatas(): \Illuminate\Support\Collection
    {
        if ($this->promocoesCache === null) {
            $this->promocoesCache = Promocao::validas()->where('modo', Promocao::AUTOMATICA)->get();
            if ($cupom = Promocao::cupomValido($this->cupom)) {
                $this->promocoesCache->push($cupom);
            }
        }

        return $this->promocoesCache;
    }

    /**
     * Itens que impedem a compra (produto inativo, fora da vitrine ou estoque menor que a quantidade).
     *
     * @return array<int, string> mensagens prontas para mostrar ao cliente
     */
    public function problemasDeEstoque(): array
    {
        $this->loadMissing('itens.produto.categoria');

        $problemas = [];
        foreach ($this->itens as $item) {
            $produto = $item->produto;
            $nome = "{$produto->nome} ({$produto->variacao()})";

            if (! $item->produtoDisponivel()) {
                $problemas[$item->id] = "{$nome} não está mais disponível. Remova o item para continuar.";
            } elseif ($item->quantidade > $produto->estoque) {
                $problemas[$item->id] = "{$nome}: você pediu {$item->quantidade}, mas só há {$produto->estoque} disponível(is). Ajuste a quantidade.";
            }
        }

        return $problemas;
    }
}
