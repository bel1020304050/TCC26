<?php

namespace App\Policies;

use App\Models\Promocao;
use App\Models\User;

/**
 * Permissões de Promoção:
 * - Administrador e Gerente: cadastram, editam, inativam e reativam.
 * - Atendente/Vendedor: só consulta (para tirar dúvidas dos clientes).
 */
class PromocaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function create(User $user): bool
    {
        return $user->isGestao();
    }

    public function update(User $user, Promocao $promocao): bool
    {
        return $user->isGestao();
    }

    public function alterarStatus(User $user, Promocao $promocao): bool
    {
        return $user->isGestao();
    }
}
