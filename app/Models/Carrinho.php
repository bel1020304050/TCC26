<?php

namespace App\Models;

use App\Support\Dinheiro;
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
        return static::where('user_id', $cliente->id)
            ->where('status', '!=', self::FINALIZADO)
            ->latest('id')
            ->first()
            ?? static::create(['user_id' => $cliente->id]);
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
