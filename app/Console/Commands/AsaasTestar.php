<?php

namespace App\Console\Commands;

use App\Pagamentos\Asaas\ClienteAsaas;
use App\Pagamentos\Asaas\ErroAsaas;
use App\Pagamentos\ProvedorIndisponivel;
use Illuminate\Console\Command;
use Throwable;

/**
 * Teste de ponta a ponta com o sandbox do Asaas, rodado no terminal:  php artisan asaas:testar
 *
 * Confere, passo a passo: chave no .env -> conexão -> cliente -> PIX (QR Code) -> boleto (linha digitável)
 * -> cartão (link da fatura) -> simulação do pagamento do PIX -> token do webhook -> limpeza.
 * Não mexe no banco da loja: só cria (e depois apaga) cobranças de teste no Asaas.
 */
class AsaasTestar extends Command
{
    protected $signature = 'asaas:testar {--manter : Não apaga as cobranças de teste no final (para ver no painel do Asaas)}';

    protected $description = 'Testa a integração com o sandbox do Asaas (PIX, boleto, cartão e simulação de pagamento)';

    private int $falhas = 0;

    private int $avisos = 0;

    /** @var array<int, string> ids das cobranças criadas, para apagar no final */
    private array $cobrancas = [];

    private ?string $cliente = null;

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Teste da integração com o Asaas</>');
        $this->line(str_repeat('-', 60));

        $config = config('mary.pagamento.asaas');
        $api = ClienteAsaas::daConfiguracao();

        // 1. Configuração
        $this->passo('1. Configuração do .env');
        $this->line('   URL: '.$config['url'].($api->ehSandbox() ? ' (sandbox)' : ''));
        $this->line('   Provedor usado na loja (PAGAMENTO_PROVEDOR): '.config('mary.pagamento.provedor'));
        if (! $config['chave']) {
            $this->falhou('ASAAS_API_KEY está vazia. Coloque a chave do sandbox no .env (entre aspas simples) e rode "php artisan config:clear".');

            return $this->resumo();
        }
        // Segurança: mostra só o começo da chave, nunca ela inteira
        $this->ok('Chave encontrada: '.substr($config['chave'], 0, 8).'... ('.strlen($config['chave']).' caracteres)');
        if (! $api->ehSandbox()) {
            $this->falhou('A URL não é do sandbox. Este teste só roda no ambiente de testes (https://api-sandbox.asaas.com/v3).');

            return $this->resumo();
        }

        // 2. Conexão e chave
        $this->passo('2. Conexão e autenticação');
        if (! $this->tentar(fn () => $api->get('/customers', ['limit' => 1]), 'Conectou e a chave foi aceita.')) {
            return $this->resumo(); // sem conexão não adianta continuar
        }

        try {
            // 3. Cliente
            $this->passo('3. Cliente de teste');
            $cliente = $this->tentar(fn () => $api->post('/customers', [
                'name' => 'Cliente Teste Mary Modas',
                'cpfCnpj' => $this->cpfDeTeste(),
                'email' => 'cliente.teste@marymodas.com.br',
                'externalReference' => 'teste-artisan',
                'notificationDisabled' => true,
            ]), 'Cliente criado.');
            if (! $cliente) {
                return $this->resumo();
            }
            $this->cliente = $cliente['id'];
            $this->line("   id: {$this->cliente}");

            // 4. PIX
            $this->passo('4. Cobrança PIX + QR Code');
            $pix = $this->criarCobranca($api, 'PIX');
            if ($pix) {
                $qr = $this->tentar(fn () => $api->get("/payments/{$pix['id']}/pixQrCode"), 'QR Code gerado.',
                    aviso: 'Sem QR Code: cadastre uma chave PIX na conta sandbox (Minha conta > Chaves PIX). A fatura do Asaas continua funcionando.');
                if ($qr) {
                    $this->line('   copia e cola: '.substr($qr['payload'] ?? '', 0, 40).'...');
                    $this->line('   imagem (base64): '.strlen($qr['encodedImage'] ?? '').' caracteres');
                }
            }

            // 5. Boleto
            $this->passo('5. Cobrança por boleto + linha digitável');
            $boleto = $this->criarCobranca($api, 'BOLETO');
            if ($boleto) {
                $linha = $this->tentar(fn () => $api->get("/payments/{$boleto['id']}/identificationField"), 'Linha digitável gerada.');
                if ($linha) {
                    $this->line('   linha: '.($linha['identificationField'] ?? '?'));
                }
                $this->line('   PDF: '.($boleto['bankSlipUrl'] ?? '(sem link)'));
            }

            // 6. Cartão
            $this->passo('6. Cobrança por cartão (página de fatura)');
            $cartao = $this->criarCobranca($api, 'CREDIT_CARD');
            if ($cartao) {
                empty($cartao['invoiceUrl'])
                    ? $this->falhou('O Asaas não devolveu o link da fatura (invoiceUrl).')
                    : $this->ok('Link da fatura: '.$cartao['invoiceUrl']);
                $this->line('   Para testar à mão: abra o link e pague com 4444 4444 4444 4444, CVV 123 (use --manter).');
            }

            // 7. Simulação
            $this->passo('7. Simular o pagamento do PIX (sandbox)');
            if ($pix && $this->tentar(fn () => $api->post("/sandbox/payment/{$pix['id']}/confirm"), 'Confirmação enviada ao sandbox.')) {
                $status = $this->tentar(fn () => $api->get("/payments/{$pix['id']}"), 'Status consultado.')['status'] ?? '?';
                in_array($status, ['CONFIRMED', 'RECEIVED'], true)
                    ? $this->ok("Status no Asaas: {$status} -> a loja marcaria o pedido como Pago.")
                    : $this->falhou("Status no Asaas: {$status} (esperado CONFIRMED ou RECEIVED).");
            }

            // 8. Webhook
            $this->passo('8. Aviso automático (webhook)');
            if (config('mary.pagamento.asaas.webhook_token')) {
                $this->ok('ASAAS_WEBHOOK_TOKEN configurado. Endereço do aviso: '.url('/webhooks/asaas'));
                $this->line('   No localhost o Asaas não alcança o site; a tela de pagamento consulta sozinha a cada 5 segundos.');
            } else {
                $this->aviso('ASAAS_WEBHOOK_TOKEN vazio: o aviso automático fica desligado (a consulta automática da tela de pagamento funciona igual).');
            }
        } finally {
            $this->limpar($api);
        }

        return $this->resumo();
    }

    private function criarCobranca(ClienteAsaas $api, string $tipo): ?array
    {
        $cobranca = $this->tentar(fn () => $api->post('/payments', [
            'customer' => $this->cliente,
            'billingType' => $tipo,
            'value' => 10.00,
            'dueDate' => now()->addDays(3)->toDateString(),
            'description' => "Teste {$tipo} - php artisan asaas:testar",
            'externalReference' => 'teste-artisan',
        ]), 'Cobrança criada.');

        if ($cobranca) {
            $this->cobrancas[] = $cobranca['id'];
            $this->line("   id: {$cobranca['id']} · status: {$cobranca['status']}");
        }

        return $cobranca;
    }

    private function limpar(ClienteAsaas $api): void
    {
        if (! $this->cliente) {
            return;
        }

        $this->passo('9. Limpeza');
        if ($this->option('manter')) {
            $this->aviso('Cobranças mantidas (--manter). Veja em Cobranças no painel do sandbox.');

            return;
        }

        foreach ($this->cobrancas as $id) {
            try {
                $api->delete("/payments/{$id}");
            } catch (Throwable) {
                // Cobrança já paga (o PIX simulado) não pode ser apagada: fica no sandbox, sem problema
            }
        }
        try {
            $api->delete("/customers/{$this->cliente}");
            $this->ok('Cobranças e cliente de teste removidos.');
        } catch (Throwable $e) {
            $this->aviso('Não foi possível remover o cliente de teste: '.$e->getMessage());
        }
    }

    // Executa uma chamada e mostra [OK] ou [FALHOU] com a explicação do Asaas
    private function tentar(callable $chamada, string $sucesso, ?string $aviso = null): ?array
    {
        try {
            $resposta = $chamada();
            $this->ok($sucesso);

            return $resposta;
        } catch (ProvedorIndisponivel $e) {
            $this->falhou($e->getMessage().' Confira a internet e a URL do .env.');
        } catch (ErroAsaas $e) {
            if ($aviso) {
                $this->aviso($aviso.' (Asaas: '.$e->getMessage().')');
            } else {
                $dica = $e->status === 401 ? ' A chave está errada ou é de produção; use a chave do sandbox.' : '';
                $this->falhou("Asaas respondeu {$e->status}: {$e->getMessage()}.{$dica}");
            }
        }

        return null;
    }

    // CPF válido gerado na hora (o sandbox confere os dígitos verificadores)
    private function cpfDeTeste(): string
    {
        $n = array_map(fn () => random_int(0, 9), range(1, 9));
        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += $n[$i] * ($t + 1 - $i);
            }
            $n[] = ((10 * $soma) % 11) % 10;
        }

        return implode('', $n);
    }

    private function passo(string $titulo): void
    {
        $this->newLine();
        $this->line("<options=bold>{$titulo}</>");
    }

    private function ok(string $texto): void
    {
        $this->line("   <fg=green>[OK]</> {$texto}");
    }

    private function aviso(string $texto): void
    {
        $this->avisos++;
        $this->line("   <fg=yellow>[AVISO]</> {$texto}");
    }

    private function falhou(string $texto): void
    {
        $this->falhas++;
        $this->line("   <fg=red>[FALHOU]</> {$texto}");
    }

    private function resumo(): int
    {
        $this->newLine();
        $this->line(str_repeat('-', 60));
        if ($this->falhas === 0) {
            $this->line('<fg=green;options=bold>Tudo certo!</> '.($this->avisos ? "({$this->avisos} aviso(s) acima)" : '')
                .' Para usar na loja, coloque PAGAMENTO_PROVEDOR=asaas no .env e rode "php artisan config:clear".');

            return self::SUCCESS;
        }
        $this->line("<fg=red;options=bold>{$this->falhas} passo(s) falharam.</> Leia as mensagens [FALHOU] acima.");

        return self::FAILURE;
    }
}
