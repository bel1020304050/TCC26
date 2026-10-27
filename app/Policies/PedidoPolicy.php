<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

/**
 * Permissões de Pedido (seção 2.2 e story 3.19):
 * - Cliente: só os próprios pedidos (a verificação é sempre feita no servidor).
 * - Funcionário e Administrador: consultam todos.
 */
class PedidoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEquipe();
    }

    public function view(User $user, Pedido $pedido): bool
    {
        return $user->isEquipe() || $pedido->user_id === $user->id;
    }

    // 3.18: só o cliente cria pedido, a partir do próprio carrinho
    public function create(User $user): bool
    {
        return $user->isCliente();
    }
}
