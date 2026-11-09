<?php

namespace App\Policies;

use App\Models\Pagamento;
use App\Models\User;

class PagamentoPolicy
{
    // Confirmar a compensação do boleto (simulação do retorno do banco): Administrador e Gerente
    public function confirmarBoleto(User $user, Pagamento $pagamento): bool
    {
        return $user->isGestao() && $pagamento->forma === 'boleto' && $pagamento->isPendente();
    }
}
