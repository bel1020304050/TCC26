<?php

namespace App\Models;

use App\Models\Concerns\TemStatus;
use App\Support\Status;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Produto — por enquanto só a estrutura. Telas e regras completas na Fase 3 (stories 3.1 a 3.4).
 */
class Produto extends Model
{
    use HasFactory, TemStatus;

    protected $fillable = [
        'nome', 'descricao', 'categoria_id', 'fornecedor_id',
        'preco', 'estoque', 'tamanho', 'cor', 'imagem',
    ];

    protected $attributes = ['status' => Status::ATIVO];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'estoque' => 'integer',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function fornecedor(): BelongsTo
    {
        return $this->belongsTo(Fornecedor::class);
    }
}
