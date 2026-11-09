<?php

namespace App\Policies;

use App\Models\Categoria;
use App\Models\User;

/**
 * Permissões de Categoria:
 * - Administrador e Gerente: cadastrar, editar, inativar e reativar.
 * - Atendente/Vendedor: só consulta.
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
        return $user->isGestao();
    }

    public function update(User $user, Categoria $categoria): bool
    {
        return $user->isGestao();
    }

    // Inativar e reativar (exclusão lógica)
    public function alterarStatus(User $user, Categoria $categoria): bool
    {
        return $user->isGestao();
    }
}
