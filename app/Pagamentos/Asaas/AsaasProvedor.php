<?php

namespace App\Pagamentos\Asaas;

use App\Models\Pagamento;
use App\Models\User;
use App\Pagamentos\PagamentoException;
use App\Pagamentos\ProvedorPagamento;
use App\Pagamentos\RespostaProvedor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provedor de pagamento ASAAS (sandbox). Documentação: https://docs.asaas.com
 *
 * - PIX: a cobrança é criada no Asaas e mostramos o QR Code e o "copia e cola" reais na nossa tela.
 * - Boleto: mostramos a linha digitável real e o link do PDF.
 * - Cartão: o cliente digita o cartão na página de fatura do Asaas (invoiceUrl), não na nossa.
 *   Segurança: assim o número do cartão nunca passa pelo nosso servidor (é o que o PCI-DSS pede).
 * Em todos, o pedido só vira "Pago" quando o Asaas confirma: pelo aviso (webhook) ou pela consulta automática da tela.
 */
class AsaasProvedor implements ProvedorPagamento
{
    // Status do Asaas -> status do nosso pagamento
    private const STATUS = [
        'CONFIRMED' => Pagamento::APROVADO,        // cartão aprovado / pagamento confirmado
        'RECEIVED' => Pagamento::APROVADO,         // dinheiro recebido (PIX, boleto)
        'RECEIVED_IN_CASH' => Pagamento::APROVADO, // recebido "em dinheiro" (marcado à mão no Asaas)
        'REFUNDED' => Pagamento::ESTORNADO,
        'REFUND_REQUESTED' => Pagamento::ESTORNADO,
        'CHARGEBACK_REQUESTED' => Pagamento::ESTORNADO,
        'OVERDUE' => Pagamento::RECUSADO,          // venceu sem pagamento
        'DELETED' => Pagamento::RECUSADO,          // removida no Asaas
    ];

    private const FORMAS = ['pix' => 'PIX', 'boleto' => 'BOLETO', 'cartao' => 'CREDIT_CARD'];

    private const BANDEIRAS = ['VISA' => 'Visa', 'MASTERCARD' => 'Mastercard', 'ELO' => 'Elo', 'AMEX' => 'Amex', 'HIPERCARD' => 'Hipercard'];

    public function __construct(private readonly ClienteAsaas $api) {}

    public function cobrar(Pagamento $pagamento, array $dados): RespostaProvedor
    {
        // $dados (cartão) é ignorado de propósito: no Asaas o cartão é digitado na página deles
        $pedido = $pagamento->pedido;
        $cliente = $pedido->cliente;

        $cobranca = $this->criarCobranca($pagamento, $cliente);
        $guardar = ['link_pagamento' => $cobranca['invoiceUrl'] ?? null];

        $mensagem = match ($pagamento->forma) {
            'pix' => $this->dadosPix($cobranca['id'], $guardar),
            'boleto' => $this->dadosBoleto($cobranca, $guardar),
            'cartao' => 'Aguardando o pagamento com cartão na página segura do Asaas.',
        };

        return new RespostaProvedor($this->status($cobranca['status'] ?? 'PENDING'), $cobranca['id'], $mensagem, $guardar);
    }

    public function consultar(Pagamento $pagamento): RespostaProvedor
    {
        return $this->resposta($this->api->get("/payments/{$pagamento->referencia}"));
    }

    public function simularPagamento(Pagamento $pagamento): RespostaProvedor
    {
        // Segurança: "fingir que pagou" só existe no ambiente de testes
        if (! $this->api->ehSandbox()) {
            throw new PagamentoException('Simular pagamento só é permitido no sandbox do Asaas.');
        }

        $this->api->post("/sandbox/payment/{$pagamento->referencia}/confirm");

        return $this->consultar($pagamento);
    }

    public function cancelar(Pagamento $pagamento): void
    {
        if (! $pagamento->referencia) {
            return;
        }

        try {
            $pagamento->status === Pagamento::ESTORNADO || $pagamento->status === Pagamento::APROVADO
                ? $this->api->post("/payments/{$pagamento->referencia}/refund")
                : $this->api->delete("/payments/{$pagamento->referencia}");
        } catch (Throwable $e) {
            // Não trava o cancelamento na loja; fica registrado para a equipe conferir no painel do Asaas
            Log::warning("Asaas: não foi possível cancelar/estornar a cobrança {$pagamento->referencia}: {$e->getMessage()}");
        }
    }

    private function criarCobranca(Pagamento $pagamento, User $cliente): array
    {
        $reaproveitado = (bool) $cliente->asaas_cliente_id;
        $dados = fn () => [
            'customer' => $this->clienteNoAsaas($cliente),
            'billingType' => self::FORMAS[$pagamento->forma],
            'value' => (float) $pagamento->valor,
            // PIX e cartão: vence amanhã; boleto: 3 dias, como no simulado
            'dueDate' => now()->addDays($pagamento->forma === 'boleto' ? 3 : 1)->toDateString(),
            'description' => "Mary Modas - Pedido {$pagamento->pedido->numero()}",
            'externalReference' => "pedido-{$pagamento->pedido_id}-pagamento-{$pagamento->id}",
        ];

        try {
            return $this->api->post('/payments', $dados());
        } catch (ErroAsaas $e) {
            // O cliente guardado pode não existir mais no Asaas (apagado ou outra conta): cria de novo e tenta uma vez
            if (! $reaproveitado || $e->status !== 400 || ! str_contains((string) $e->codigo.$e->getMessage(), 'customer')) {
                throw $e;
            }
            $cliente->forceFill(['asaas_cliente_id' => null])->save();

            return $this->api->post('/payments', $dados());
        }
    }

    // O Asaas exige um "cliente" (com CPF) para cada cobrança. É criado no 1º pagamento e reaproveitado depois.
    private function clienteNoAsaas(User $cliente): string
    {
        if ($cliente->asaas_cliente_id) {
            return $cliente->asaas_cliente_id;
        }

        if (! $cliente->cpf) {
            throw new PagamentoException('Para pagar, informe o seu CPF em Minha conta (o Asaas exige CPF em toda cobrança).');
        }

        $criado = $this->api->post('/customers', [
            'name' => $cliente->name,
            'cpfCnpj' => $cliente->cpf,
            'email' => $cliente->email,
            'externalReference' => "cliente-{$cliente->id}",
            'notificationDisabled' => true, // o Asaas não manda e-mail/SMS: a loja já avisa o cliente
        ]);

        $cliente->forceFill(['asaas_cliente_id' => $criado['id']])->save();

        return $criado['id'];
    }

    private function dadosPix(string $id, array &$guardar): string
    {
        try {
            $qr = $this->api->get("/payments/{$id}/pixQrCode");
            $guardar['pix_codigo'] = $qr['payload'] ?? null;
            $guardar['pix_qr'] = $qr['encodedImage'] ?? null;

            return 'Aguardando o pagamento do PIX.';
        } catch (ErroAsaas) {
            // Conta sem chave PIX cadastrada: o QR não sai, mas a fatura do Asaas continua valendo
            return 'Aguardando o pagamento do PIX (QR Code disponível na fatura do Asaas).';
        }
    }

    private function dadosBoleto(array $cobranca, array &$guardar): string
    {
        $guardar['boleto_vencimento'] = $cobranca['dueDate'] ?? now()->addDays(3)->toDateString();
        $guardar['link_pagamento'] = $cobranca['bankSlipUrl'] ?? $guardar['link_pagamento'];

        try {
            $linha = $this->api->get("/payments/{$cobranca['id']}/identificationField");
            $guardar['boleto_linha'] = $linha['identificationField'] ?? null;
        } catch (ErroAsaas) {
            // Sem a linha, o cliente ainda tem o PDF do boleto
        }

        return 'Aguardando a compensação do boleto.';
    }

    private function resposta(array $cobranca): RespostaProvedor
    {
        $status = $this->status($cobranca['status'] ?? 'PENDING');
        $dados = [];

        // Cartão pago na fatura: guardamos só a bandeira e o final, como no simulado
        if (! empty($cobranca['creditCard']['creditCardNumber'])) {
            $dados['cartao_final'] = substr((string) $cobranca['creditCard']['creditCardNumber'], -4);
            $marca = strtoupper((string) ($cobranca['creditCard']['creditCardBrand'] ?? ''));
            $dados['cartao_bandeira'] = self::BANDEIRAS[$marca] ?? 'Cartão';
        }

        $mensagem = match ($cobranca['status'] ?? null) {
            'OVERDUE' => 'A cobrança venceu sem pagamento.',
            'DELETED' => 'A cobrança foi cancelada no Asaas.',
            'REFUNDED', 'REFUND_REQUESTED' => 'O valor foi estornado pelo Asaas.',
            'CHARGEBACK_REQUESTED' => 'O cliente contestou a compra no cartão (chargeback).',
            default => $status === Pagamento::APROVADO ? 'Pagamento aprovado.' : 'Ainda não identificamos o pagamento.',
        };

        return new RespostaProvedor($status, (string) $cobranca['id'], $mensagem, $dados);
    }

    private function status(string $statusAsaas): string
    {
        return self::STATUS[$statusAsaas] ?? Pagamento::PENDENTE;
    }
}
