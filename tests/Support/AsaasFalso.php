<?php

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Imitação do sandbox do Asaas para os testes (nenhuma chamada sai para a internet).
 * Guarda as cobranças em memória e responde como a API real: /customers, /payments,
 * /pixQrCode, /identificationField, /sandbox/payment/{id}/confirm, DELETE e /refund.
 */
class AsaasFalso
{
    /** @var array<string, array> */
    public array $cobrancas = [];

    /** @var array<string, array> */
    public array $clientes = [];

    /** @var array<int, string> "MÉTODO /caminho" de cada chamada recebida */
    public array $chamadas = [];

    public bool $semChavePix = false;

    public bool $foraDoAr = false;

    public static function ligar(): self
    {
        $falso = new self;
        config(['mary.pagamento.provedor' => 'asaas']);
        Http::fake(fn (Request $r) => $falso->responder($r));

        return $falso;
    }

    // Muda o status de uma cobrança "lá no Asaas" (ex.: cliente pagou a fatura do cartão)
    public function mudarStatus(string $id, string $status, array $extra = []): void
    {
        $this->cobrancas[$id] = array_merge($this->cobrancas[$id], ['status' => $status], $extra);
    }

    public function ultimaCobranca(): array
    {
        return end($this->cobrancas);
    }

    private function responder(Request $r)
    {
        if ($this->foraDoAr) {
            return Http::response(['errors' => [['code' => 'erro', 'description' => 'Instabilidade']]], 503);
        }
        // Como no Asaas: sem a chave certa no cabeçalho "access_token", responde 401
        if (($r->header('access_token')[0] ?? null) !== 'chave-de-teste') {
            return Http::response(['errors' => [['code' => 'invalid_access_token', 'description' => 'A chave de API fornecida é inválida']]], 401);
        }

        $caminho = '/'.ltrim(str_replace(config('mary.pagamento.asaas.url'), '', strtok($r->url(), '?')), '/');
        $this->chamadas[] = $r->method().' '.$caminho;
        $dados = $r->data();

        // Clientes
        if ($r->method() === 'GET' && $caminho === '/customers') {
            return Http::response(['data' => array_values($this->clientes), 'totalCount' => count($this->clientes)]);
        }
        if ($r->method() === 'POST' && $caminho === '/customers') {
            if (empty($dados['cpfCnpj'])) {
                return Http::response(['errors' => [['code' => 'invalid_cpfCnpj', 'description' => 'O CPF/CNPJ informado é inválido.']]], 400);
            }
            $id = 'cus_'.str_pad((string) (count($this->clientes) + 1), 12, '0', STR_PAD_LEFT);
            $this->clientes[$id] = ['id' => $id] + $dados;

            return Http::response($this->clientes[$id]);
        }
        if ($r->method() === 'DELETE' && str_starts_with($caminho, '/customers/')) {
            unset($this->clientes[substr($caminho, 11)]);

            return Http::response(['deleted' => true]);
        }

        // Cobranças
        if ($r->method() === 'POST' && $caminho === '/payments') {
            if (! isset($this->clientes[$dados['customer'] ?? ''])) {
                return Http::response(['errors' => [['code' => 'invalid_customer', 'description' => 'Customer inválido ou não informado.']]], 400);
            }
            $id = 'pay_'.str_pad((string) (count($this->cobrancas) + 1), 12, '0', STR_PAD_LEFT);
            $this->cobrancas[$id] = [
                'id' => $id, 'status' => 'PENDING', 'value' => $dados['value'], 'billingType' => $dados['billingType'],
                'dueDate' => $dados['dueDate'], 'externalReference' => $dados['externalReference'] ?? null,
                'invoiceUrl' => "https://sandbox.asaas.com/i/{$id}",
                'bankSlipUrl' => $dados['billingType'] === 'BOLETO' ? "https://sandbox.asaas.com/b/pdf/{$id}" : null,
            ];

            return Http::response($this->cobrancas[$id]);
        }

        if (preg_match('#^/payments/(pay_\w+)(/\w+)?$#', $caminho, $m) || preg_match('#^/sandbox/payment/(pay_\w+)/confirm$#', $caminho, $m)) {
            $id = $m[1];
            if (! isset($this->cobrancas[$id])) {
                return Http::response(['errors' => [['code' => 'not_found', 'description' => 'Cobrança não encontrada.']]], 404);
            }
            $acao = $m[2] ?? (str_starts_with($caminho, '/sandbox') ? '/confirm' : '');

            return match ([$r->method(), $acao]) {
                ['GET', ''] => Http::response($this->cobrancas[$id]),
                ['GET', '/pixQrCode'] => $this->semChavePix
                    ? Http::response(['errors' => [['code' => 'invalid_action', 'description' => 'Nenhuma chave Pix cadastrada.']]], 404)
                    : Http::response(['encodedImage' => base64_encode('PNG-FALSO'), 'payload' => "00020126PIX-ASAAS-{$id}", 'expirationDate' => now()->addDay()->toDateTimeString()]),
                ['GET', '/identificationField'] => Http::response(['identificationField' => '00190000090123456789012345678901234567890123', 'nossoNumero' => '123', 'barCode' => '001']),
                ['POST', '/confirm'] => $this->confirmar($id),
                ['POST', '/refund'] => $this->mudarEResponder($id, 'REFUNDED'),
                ['DELETE', ''] => $this->apagar($id),
                default => Http::response(['errors' => [['code' => 'x', 'description' => 'Rota inexistente']]], 404),
            };
        }

        return Http::response(['errors' => [['code' => 'x', 'description' => "Rota não imitada: {$r->method()} {$caminho}"]]], 404);
    }

    private function confirmar(string $id)
    {
        $tipo = $this->cobrancas[$id]['billingType'];

        return $this->mudarEResponder($id, $tipo === 'CREDIT_CARD' ? 'CONFIRMED' : 'RECEIVED');
    }

    private function mudarEResponder(string $id, string $status)
    {
        $this->mudarStatus($id, $status);

        return Http::response($this->cobrancas[$id]);
    }

    private function apagar(string $id)
    {
        if ($this->cobrancas[$id]['status'] !== 'PENDING') {
            return Http::response(['errors' => [['code' => 'invalid_action', 'description' => 'Cobrança paga não pode ser removida.']]], 400);
        }
        $this->mudarStatus($id, 'DELETED');

        return Http::response(['deleted' => true, 'id' => $id]);
    }
}
