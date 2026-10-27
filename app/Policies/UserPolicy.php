<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permissões do CRUD de Usuários internos — stories 3.5 a 3.8.
 * Só o Administrador gerencia a equipe. Clientes não são gerenciados aqui
 * (eles cuidam da própria conta — stories 3.9 a 3.12).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $alvo): bool
    {
        return $user->isAdmin() && $alvo->isEquipe();
    }

    // Inativar/reativar: Administrador, só equipe interna e nunca a própria conta (3.8)
    public function alterarStatus(User $user, User $alvo): bool
    {
        return $user->isAdmin() && $alvo->isEquipe() && $user->isNot($alvo);
    }
}
