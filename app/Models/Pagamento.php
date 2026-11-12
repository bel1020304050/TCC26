<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Uma tentativa de pagamento de um pedido. Um pedido pode ter várias (ex.: cartão recusado e depois PIX).
 */
class Pagamento extends Model
{
    public const PENDENTE = 'pendente';
    public const APROVADO = 'aprovado';
    public const RECUSADO = 'recusado';
    public const ESTORNADO = 'estornado';

    public const STATUS = [
        self::PENDENTE => 'Pendente',
        self::APROVADO => 'Aprovado',
        self::RECUSADO => 'Recusado',
        self::ESTORNADO => 'Estornado',
    ];

    public const CORES = [
        self::PENDENTE => 'text-bg-warning',
        self::APROVADO => 'text-bg-success',
        self::RECUSADO => 'text-bg-danger',
        self::ESTORNADO => 'text-bg-secondary',
    ];

    // Tudo é definido pelo servidor (nada vem direto do formulário)
    protected $fillable = [];

    protected $attributes = ['status' => self::PENDENTE];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'boleto_vencimento' => 'date',
            'processado_em' => 'datetime',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por');
    }

    public function isPendente(): bool
    {
        return $this->status === self::PENDENTE;
    }

    public function formaLabel(): string
    {
        return Pedido::FORMAS_PAGAMENTO[$this->forma] ?? $this->forma;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function corStatus(): string
    {
        return self::CORES[$this->status] ?? 'text-bg-secondary';
    }

    // "Visa final 1111", "Vence em 30/10/2026"...
    public function detalhe(): string
    {
        return match ($this->forma) {
            'cartao' => $this->cartao_final ? trim("{$this->cartao_bandeira} final {$this->cartao_final}") : '',
            'boleto' => $this->boleto_vencimento ? 'Vence em '.$this->boleto_vencimento->format('d/m/Y') : '',
            default => '',
        };
    }

    public function valorFormatado(): string
    {
        return Dinheiro::formatar($this->valor);
    }
}
