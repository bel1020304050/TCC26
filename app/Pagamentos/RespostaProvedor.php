<?php

namespace App\Pagamentos;

/**
 * Resposta do provedor: Aprovado, Recusado ou Pendente (etapa 4 do processo 3.31),
 * mais os dados que podem ser guardados (código PIX, linha do boleto, bandeira e final do cartão).
 */
final class RespostaProvedor
{
    /**
     * @param  array<string, mixed>  $dados  colunas extras para gravar no pagamento
     */
    public function __construct(
        public readonly string $status,
        public readonly string $referencia,
        public readonly ?string $mensagem = null,
        public readonly array $dados = [],
    ) {}
}
