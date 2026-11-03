<?php

namespace App\Policies;

use App\Models\Promocao;
use App\Models\User;

/**
 * Permissões de Promoção (seção 2.2 e stories 3.22 a 3.25):
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
