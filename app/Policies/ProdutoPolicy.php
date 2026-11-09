<?php

namespace App\Policies;

use App\Models\Produto;
use App\Models\User;

/**
 * Permissões de Produto:
 * - Administrador e Gerente: tudo, inclusive inativar e reativar.
 * - Atendente/Vendedor: cadastrar, consultar e editar (inclui repor estoque).
 * - Cliente: só a vitrine (não usa estas permissões).
 */
class ProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function create(User $user): bool
    {
        return $user->isEquipe();
    }

    public function update(User $user, Produto $produto): bool
    {
        return $user->isEquipe();
    }

    // Inativar e reativar: Administrador e Gerente
    public function alterarStatus(User $user, Produto $produto): bool
    {
        return $user->isGestao();
    }
}
