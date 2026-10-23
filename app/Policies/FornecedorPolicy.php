<?php

namespace App\Policies;

use App\Models\Fornecedor;
use App\Models\User;

/**
 * Permissões de Fornecedor (tabela da seção 2.2):
 * - Administrador: CRUD completo.
 * - Funcionário: só consulta.
 * - Cliente: sem acesso.
 */
class FornecedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Fornecedor $fornecedor): bool
    {
        return $user->isAdmin();
    }

    // Inativar e reativar (exclusão lógica)
    public function alterarStatus(User $user, Fornecedor $fornecedor): bool
    {
        return $user->isAdmin();
    }
}
