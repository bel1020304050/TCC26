<?php

namespace App\Policies;

use App\Models\Fornecedor;
use App\Models\User;

/**
 * Permissões de Fornecedor:
 * - Administrador e Gerente: cadastrar, editar, inativar e reativar.
 * - Atendente/Vendedor: só consulta.
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
        return $user->isGestao();
    }

    public function update(User $user, Fornecedor $fornecedor): bool
    {
        return $user->isGestao();
    }

    // Inativar e reativar (exclusão lógica)
    public function alterarStatus(User $user, Fornecedor $fornecedor): bool
    {
        return $user->isGestao();
    }
}
