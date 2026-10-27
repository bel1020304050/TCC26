<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Uma linha por mudança de status (quando, quem e qual status)
class PedidoHistorico extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['status', 'user_id', 'observacao'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function statusLabel(): string
    {
        return Pedido::STATUS[$this->status] ?? $this->status;
    }
}
