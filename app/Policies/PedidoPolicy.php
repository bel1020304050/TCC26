<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;

/**
 * Permissões de Pedido (seção 2.2, stories 3.19 a 3.21):
 * - Cliente: só os próprios pedidos; cancela o próprio pedido enquanto Aguardando Pagamento.
 * - Funcionário: consulta todos, atualiza status e cancela pedidos Aguardando Pagamento.
 * - Administrador: tudo do Funcionário + cancelar pedido Pago ou Em Separação (com estorno).
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

    // 3.20: Administrador e Funcionário
    public function atualizarStatus(User $user, Pedido $pedido): bool
    {
        return $user->isEquipe();
    }

    // 3.21: depende do status e do perfil
    public function cancelar(User $user, Pedido $pedido): bool
    {
        return match ($pedido->status) {
            Pedido::AGUARDANDO_PAGAMENTO => $user->isEquipe() || $pedido->user_id === $user->id,
            Pedido::PAGO, Pedido::EM_SEPARACAO => $user->isAdmin(),
            default => false, // Enviado, Entregue e Cancelado
        };
    }

    // Mensagem para quando o cancelamento não é permitido (CT-3-21-02)
    public static function motivoBloqueioCancelamento(User $user, Pedido $pedido): ?string
    {
        return match (true) {
            in_array($pedido->status, [Pedido::ENVIADO, Pedido::ENTREGUE], true) => 'Pedidos Enviados ou Entregues não podem ser cancelados (troca e devolução estão fora do escopo do MVP).',
            $pedido->status === Pedido::CANCELADO => 'Este pedido já está cancelado.',
            in_array($pedido->status, [Pedido::PAGO, Pedido::EM_SEPARACAO], true) && ! $user->isAdmin() => 'Pedidos pagos só podem ser cancelados pelo Administrador, com estorno.',
            default => null,
        };
    }
}
