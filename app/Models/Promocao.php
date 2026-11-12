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
 * Promoção automática ou cupom de desconto.
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

    // Situação mostrada nas telas, calculada pela data do servidor (não pelo relógio do computador do usuário)
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
     * Regra: valor fixo nunca passa do valor dos itens, para o total não ficar negativo.
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

    /**
     * Melhor promoção automática para um produto (a que dá o maior desconto).
     * $automaticas = promoções automáticas Ativas e Vigentes, buscadas uma vez só (vitrine e página do produto).
     */
    public static function melhorAutomatica(Produto $produto, \Illuminate\Support\Collection $automaticas): ?self
    {
        return $automaticas->filter(fn (self $p) => $p->aplicaA($produto))
            ->sortByDesc(fn (self $p) => $p->descontoSobre((float) $produto->preco))
            ->first();
    }

    // Cupons que o cliente pode usar agora, para a lista "Cupons disponíveis" do carrinho
    public static function cuponsDisponiveis(): \Illuminate\Database\Eloquent\Collection
    {
        return static::validas()->where('modo', self::CUPOM)->with(['categoria:id,nome', 'produto:id,nome,tamanho,cor'])
            ->orderBy('data_fim')->get();
    }

    // "MARY20 · 20% em todos os produtos · válido até 15/11"
    public function descricaoCupom(): string
    {
        $onde = match ($this->aplicacao) {
            self::CATEGORIA => 'em '.($this->categoria?->nome ?? 'uma categoria'),
            self::PRODUTO => 'em '.($this->produto ? $this->produto->nome.' ('.$this->produto->variacao().')' : 'um produto'),
            default => 'em todos os produtos',
        };

        $desconto = $this->tipo === self::PERCENTUAL ? $this->descontoLabel() : $this->descontoLabel().' de desconto';

        return $this->codigo.' · '.$desconto.' '.$onde.' · válido até '.$this->data_fim->format('d/m');
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
