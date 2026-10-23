<?php

namespace App\Policies;

use App\Models\Produto;
use App\Models\User;

/**
 * Permissões de Produto (tabela da seção 2.2):
 * - Administrador: tudo, inclusive inativar e reativar.
 * - Funcionário: cadastrar, consultar e editar (inclui repor estoque).
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

    // Inativar e reativar: só o Administrador (story 3.4)
    public function alterarStatus(User $user, Produto $produto): bool
    {
        return $user->isAdmin();
    }
}
