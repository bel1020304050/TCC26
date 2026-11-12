<?php

namespace App\Pagamentos\Asaas;

use RuntimeException;

// O Asaas recusou o pedido (dado inválido, chave errada, cobrança inexistente...). Traz o código HTTP e a descrição dele.
class ErroAsaas extends RuntimeException
{
    public function __construct(string $mensagem, public readonly int $status, public readonly ?string $codigo = null)
    {
        parent::__construct($mensagem, $status);
    }
}
