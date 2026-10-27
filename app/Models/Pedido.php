<?php

namespace App\Models;

use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido — CRUD de Pedidos (3.18 criar, 3.19 consultar, 3.20 status, 3.21 cancelar).
 * Fluxo (seção 2.3): Aguardando Pagamento → Pago → Em Separação → Enviado → Entregue; Cancelado é final.
 */
class Pedido extends Model
{
    public const AGUARDANDO_PAGAMENTO = 'aguardando_pagamento';
    public const PAGO = 'pago';
    public const EM_SEPARACAO = 'em_separacao';
    public const ENVIADO = 'enviado';
    public const ENTREGUE = 'entregue';
    public const CANCELADO = 'cancelado';

    public const STATUS = [
        self::AGUARDANDO_PAGAMENTO => 'Aguardando Pagamento',
        self::PAGO => 'Pago',
        self::EM_SEPARACAO => 'Em Separação',
        self::ENVIADO => 'Enviado',
        self::ENTREGUE => 'Entregue',
        self::CANCELADO => 'Cancelado',
    ];

    // Cor do selo de cada status (classes do Bootstrap)
    public const CORES = [
        self::AGUARDANDO_PAGAMENTO => 'text-bg-warning',
        self::PAGO => 'text-bg-success',
        self::EM_SEPARACAO => 'text-bg-primary',
        self::ENVIADO => 'text-bg-info',
        self::ENTREGUE => 'text-bg-success',
        self::CANCELADO => 'text-bg-danger',
    ];

    public const FORMAS_PAGAMENTO = ['pix' => 'PIX', 'cartao' => 'Cartão de Crédito', 'boleto' => 'Boleto Bancário'];

    // Status, valores e endereço não entram por preenchimento em massa: só o servidor define
    protected $fillable = [];

    protected $attributes = ['status' => self::AGUARDANDO_PAGAMENTO, 'desconto' => 0];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'desconto' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function carrinho(): BelongsTo
    {
        return $this->belongsTo(Carrinho::class);
    }

    public function itens(): HasMany
    {
        return $this->hasMany(PedidoItem::class);
    }

    public function historico(): HasMany
    {
        return $this->hasMany(PedidoHistorico::class)->orderBy('created_at')->orderBy('id');
    }

    // Grava a mudança de status junto com data/hora e responsável
    public function registrarStatus(string $status, ?User $responsavel = null, ?string $observacao = null): void
    {
        $this->status = $status;
        $this->save();

        $this->historico()->create([
            'status' => $status,
            'user_id' => $responsavel?->id,
            'observacao' => $observacao,
        ]);
    }

    public function numero(): string
    {
        return '#'.$this->id;
    }

    public function statusLabel(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    public function corStatus(): string
    {
        return self::CORES[$this->status] ?? 'text-bg-secondary';
    }

    public function formaPagamentoLabel(): string
    {
        return self::FORMAS_PAGAMENTO[$this->forma_pagamento] ?? 'Ainda não pago';
    }

    public function enderecoLinha1(): string
    {
        return $this->rua.', '.$this->numero.($this->complemento ? ' – '.$this->complemento : '');
    }

    public function enderecoLinha2(): string
    {
        return "{$this->bairro} · {$this->cidade}/{$this->uf} · CEP {$this->cep}";
    }

    public function valor(string $campo): string
    {
        return Dinheiro::formatar($this->{$campo});
    }
}
