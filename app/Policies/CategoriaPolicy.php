<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

/**
 * Permissões de Categoria (tabela da seção 2.2):
 * - Administrador: CRUD completo.
 * - Funcionário: só consulta.
 * - Cliente: sem acesso.
 */
class CategoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $user->isAdmin();
    }

    // Inativar e reativar (exclusão lógica)
    public function alterarStatus(User $user, Categoria $categoria): bool
    {
        return $user->isAdmin();
    }
}
