<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permissões do cadastro da equipe.
 * Só o Administrador gerencia a equipe. Clientes não são gerenciados aqui
 * (cada cliente cuida da própria conta em "Minha conta").
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

    // Inativar/reativar: só o Administrador, só contas da equipe e nunca a própria conta
    public function alterarStatus(User $user, User $alvo): bool
    {
        return $user->isAdmin() && $alvo->isEquipe() && $user->isNot($alvo);
    }
}
