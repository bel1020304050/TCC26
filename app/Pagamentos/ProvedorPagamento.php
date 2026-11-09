<?php

namespace App\Pagamentos;

use App\Models\Pagamento;

/**
 * "Tomada" do provedor de pagamento (padrão Strategy).
 *
 * O resto do sistema só conhece esta interface. Hoje quem responde é o PagamentoSimulado;
 * para usar o Mercado Pago, basta criar uma classe MercadoPagoProvedor que implemente
 * este mesmo método e trocar a ligação no AppServiceProvider.
 */
interface ProvedorPagamento
{
    /**
     * Envia a cobrança ao provedor e devolve a resposta dele.
     *
     * @param  array<string, string>  $dados  dados digitados no formulário (ex.: cartão). Não devem ser guardados.
     *
     * @throws ProvedorIndisponivel quando o provedor não responde (falha temporária)
     */
    public function cobrar(Pagamento $pagamento, array $dados): RespostaProvedor;
}
