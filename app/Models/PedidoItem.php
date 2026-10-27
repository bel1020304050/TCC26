<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Cópia do produto no momento da compra (nome, tamanho, cor e preço "congelados")
class PedidoItem extends Model
{
    protected $table = 'pedido_itens';

    public $timestamps = false;

    protected $fillable = ['produto_id', 'nome', 'tamanho', 'cor', 'quantidade', 'preco_unitario', 'subtotal'];

    protected function casts(): array
    {
        return ['quantidade' => 'integer', 'preco_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function descricao(): string
    {
        return "{$this->nome} — {$this->tamanho} / {$this->cor}";
    }

    public function valor(string $campo): string
    {
        return Dinheiro::formatar($this->{$campo});
    }
}
