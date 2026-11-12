<?php

namespace App\Pagamentos;

use App\Models\Pagamento;

/**
 * "Tomada" do provedor de pagamento (padrão Strategy).
 *
 * O resto do sistema só conhece esta interface. Há duas classes que se encaixam nela:
 * - PagamentoSimulado: funciona sem internet (MVP)
 * - AsaasProvedor: cobranças de teste reais no sandbox do Asaas
 * Qual delas é usada é decidido no .env (PAGAMENTO_PROVEDOR) e ligado no AppServiceProvider.
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

    // Pergunta ao provedor como está a cobrança agora (consulta automática da tela e aviso/webhook)
    public function consultar(Pagamento $pagamento): RespostaProvedor;

    // Ambiente de testes: faz de conta que o cliente pagou o PIX/boleto
    public function simularPagamento(Pagamento $pagamento): RespostaProvedor;

    // Cobrança que deixou de valer: cancela a pendente ou estorna a aprovada. Melhor esforço (não lança erro).
    public function cancelar(Pagamento $pagamento): void;
}
