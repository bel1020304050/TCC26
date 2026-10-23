<?php

namespace App\Models\Concerns;

use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;

/**
 * Comportamento comum dos cadastros com exclusão lógica (Ativo/Inativo).
 * Em vez de apagar, o registro é inativado e pode ser reativado depois.
 */
trait TemStatus
{
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where($this->getTable().'.status', Status::ATIVO);
    }

    public function isAtivo(): bool
    {
        return $this->status === Status::ATIVO;
    }

    public function statusLabel(): string
    {
        return Status::label($this->status);
    }
}
