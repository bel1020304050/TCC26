<?php

namespace App\Models;

use App\Models\Concerns\TemStatus;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Produto da loja. Cada combinação de tamanho e cor é um registro próprio
 * (ex.: "Saia Midi M Bege" e "Saia Midi G Bege" são dois produtos), com estoque próprio.
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

    /**
     * Regra: produtos que podem aparecer na vitrine são os
     * ativos, com estoque maior que zero e de categoria ativa.
     */
    public function scopeNaVitrine(Builder $query): Builder
    {
        return $query->ativos()
            ->where('estoque', '>', 0)
            ->whereHas('categoria', fn (Builder $q) => $q->ativos());
    }

    // URL da imagem; sem imagem cadastrada, usa a imagem padrão "Sem imagem"
    public function imagemUrl(): string
    {
        return $this->imagem
            ? Storage::disk('public')->url($this->imagem)
            : asset('img/produto-sem-imagem.svg');
    }

    // 129.9 -> "R$ 129,90"
    public function precoFormatado(): string
    {
        return 'R$ '.number_format((float) $this->preco, 2, ',', '.');
    }

    // "M / Preto"
    public function variacao(): string
    {
        return $this->tamanho.' / '.$this->cor;
    }
}
