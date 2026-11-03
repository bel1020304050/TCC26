<?php

namespace App\Models;

use App\Models\Concerns\TemStatus;
use App\Support\Dinheiro;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Promoção ou cupom — stories 3.22 a 3.25.
 * Pense numa etiqueta de desconto: o "status" diz se a loja deixou a etiqueta ligada (Ativa/Inativa)
 * e as datas dizem se ela já começou ou acabou (Agendada, Vigente, Expirada).
 * Só dá desconto quando está Ativa E Vigente (e, no modo Cupom, quando o cliente digita o código).
 */
class Promocao extends Model
{
    use HasFactory, TemStatus;

    protected $table = 'promocoes';

    public const AUTOMATICA = 'automatica';
    public const CUPOM = 'cupom';
    public const PERCENTUAL = 'percentual';
    public const VALOR = 'valor';
    public const GERAL = 'geral';
    public const PRODUTO = 'produto';
    public const CATEGORIA = 'categoria';

    // Situação mostrada nas telas (calculada pela data do servidor — RNF.01 da 3.23)
    public const SITUACOES = [
        'vigente' => 'Vigente',
        'agendada' => 'Agendada',
        'expirada' => 'Expirada',
        'inativa' => 'Inativa',
    ];

    public const CORES = [
        'vigente' => 'text-bg-success',
        'agendada' => 'text-bg-warning',
        'expirada' => 'text-bg-secondary',
        'inativa' => 'text-bg-danger',
    ];

    protected $fillable = [
        'nome', 'modo', 'codigo', 'tipo', 'valor', 'data_inicio', 'data_fim', 'aplicacao', 'produto_id', 'categoria_id',
    ];

    protected $attributes = ['status' => Status::ATIVO];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_inicio' => 'date',
            'data_fim' => 'date',
        ];
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    // Código sempre em maiúsculas e sem espaços ("mary20 " vira "MARY20")
    protected function codigo(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn (?string $valor) => $valor === null || trim($valor) === '' ? null : mb_strtoupper(trim($valor)),
        );
    }

    // Cupom que o cliente pode usar agora (Ativo e Vigente)
    public static function cupomValido(?string $codigo): ?self
    {
        if (! $codigo) {
            return null;
        }

        return static::validas()->where('modo', self::CUPOM)->where('codigo', mb_strtoupper(trim($codigo)))->first();
    }

    // ---------- Situação ----------

    public function situacao(): string
    {
        $hoje = today();

        return match (true) {
            $this->status !== Status::ATIVO => 'inativa',
            $hoje->lt($this->data_inicio) => 'agendada',
            $hoje->gt($this->data_fim) => 'expirada',
            default => 'vigente',
        };
    }

    public function situacaoLabel(): string
    {
        return self::SITUACOES[$this->situacao()];
    }

    public function corSituacao(): string
    {
        return self::CORES[$this->situacao()];
    }

    // Ativa e dentro do período: pode dar desconto
    public function scopeValidas(Builder $query): Builder
    {
        $hoje = today()->toDateString();

        return $query->ativos()->whereDate('data_inicio', '<=', $hoje)->whereDate('data_fim', '>=', $hoje);
    }

    public function scopeSituacao(Builder $query, string $situacao): Builder
    {
        $hoje = today()->toDateString();

        return match ($situacao) {
            'inativa' => $query->where('status', Status::INATIVO),
            'agendada' => $query->ativos()->whereDate('data_inicio', '>', $hoje),
            'expirada' => $query->ativos()->whereDate('data_fim', '<', $hoje),
            'vigente' => $query->validas(),
            default => $query,
        };
    }

    // ---------- Cálculo ----------

    // O produto entra nesta promoção? (aplicação Geral, Produto ou Categoria)
    public function aplicaA(Produto $produto): bool
    {
        return match ($this->aplicacao) {
            self::PRODUTO => $produto->id === $this->produto_id,
            self::CATEGORIA => $produto->categoria_id === $this->categoria_id,
            default => true,
        };
    }

    /**
     * Desconto sobre o valor dos itens elegíveis.
     * Valor fixo nunca passa do valor elegível, para o total não ficar negativo (regra da 3.30).
     */
    public function descontoSobre(float $valorElegivel): float
    {
        if ($valorElegivel <= 0) {
            return 0.0;
        }

        $desconto = $this->tipo === self::PERCENTUAL
            ? $valorElegivel * (float) $this->valor / 100
            : (float) $this->valor;

        return Dinheiro::arredondar(min($desconto, $valorElegivel));
    }

    // Preço do produto com esta promoção (só faz sentido mostrar na vitrine quando é percentual)
    public function precoCom(Produto $produto): float
    {
        return Dinheiro::arredondar((float) $produto->preco * (1 - (float) $this->valor / 100));
    }

    // ---------- Textos ----------

    public function descontoLabel(): string
    {
        return $this->tipo === self::PERCENTUAL
            ? rtrim(rtrim(number_format((float) $this->valor, 2, ',', ''), '0'), ',').'%'
            : Dinheiro::formatar($this->valor);
    }

    public function seloLabel(): string
    {
        return $this->tipo === self::PERCENTUAL ? '-'.$this->descontoLabel() : $this->descontoLabel().' OFF';
    }

    public function modoLabel(): string
    {
        return $this->modo === self::CUPOM ? 'Cupom' : 'Automática';
    }

    public function aplicacaoLabel(): string
    {
        return match ($this->aplicacao) {
            self::PRODUTO => 'Produto: '.($this->produto ? $this->produto->nome.' ('.$this->produto->variacao().')' : '—'),
            self::CATEGORIA => 'Categoria: '.($this->categoria?->nome ?? '—'),
            default => 'Geral',
        };
    }

    public function periodoLabel(): string
    {
        return $this->data_inicio->format('d/m/Y').' a '.$this->data_fim->format('d/m/Y');
    }

    public function foiUsada(): bool
    {
        return $this->pedidos()->exists();
    }
}
