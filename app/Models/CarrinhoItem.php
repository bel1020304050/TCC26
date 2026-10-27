<?php

namespace App\Models;

use App\Support\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarrinhoItem extends Model
{
    protected $table = 'carrinho_itens';

    protected $fillable = ['produto_id', 'quantidade'];

    protected function casts(): array
    {
        return ['quantidade' => 'integer'];
    }

    public function carrinho(): BelongsTo
    {
        return $this->belongsTo(Carrinho::class);
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function subtotal(): float
    {
        return round((float) $this->produto->preco * $this->quantidade, 2);
    }

    // Mesma regra da vitrine: produto e categoria ativos e com estoque
    public function produtoDisponivel(): bool
    {
        $produto = $this->produto;

        return $produto->status === Status::ATIVO
            && $produto->estoque > 0
            && $produto->categoria?->status === Status::ATIVO;
    }
}
