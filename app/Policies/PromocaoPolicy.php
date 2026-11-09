<?php

namespace App\Policies;

use App\Models\Promocao;
use App\Models\User;

/**
 * Permissões de Promoção:
 * - Administrador: cadastra, edita, inativa e reativa.
 * - Funcionário: só consulta (para tirar dúvidas dos clientes).
 */
class PromocaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Promocao $promocao): bool
    {
        return $user->isAdmin();
    }

    public function alterarStatus(User $user, Promocao $promocao): bool
    {
        return $user->isAdmin();
    }
}
