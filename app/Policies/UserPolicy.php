<?php

namespace App\Policies;

use App\Models\User;

/**
 * Permissões do cadastro da equipe.
 * O Administrador gerencia a equipe. O Gerente só vê a lista e redefine a senha dos Atendentes.
 * Clientes não são gerenciados aqui (cada cliente cuida da própria conta em "Minha conta").
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGestao();
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

    /**
     * Gerar senha provisória para quem esqueceu a senha.
     * Regra: o Administrador redefine a de qualquer pessoa da equipe; o Gerente, só a dos Atendentes.
     * Segurança: ninguém redefine a própria senha por aqui (para isso existe "Minha conta"),
     * e o Gerente não redefine a do Administrador (senão poderia entrar como ele).
     */
    public function redefinirSenha(User $user, User $alvo): bool
    {
        if ($user->is($alvo) || ! $alvo->isEquipe() || ! $alvo->isAtivo()) {
            return false;
        }

        return $user->isAdmin() || ($user->isGerente() && $alvo->isAtendente());
    }
}
