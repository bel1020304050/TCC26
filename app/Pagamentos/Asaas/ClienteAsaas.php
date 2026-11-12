<?php

namespace App\Pagamentos\Asaas;

use App\Pagamentos\ProvedorIndisponivel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Conversa com a API do Asaas (REST + JSON). Só sabe fazer GET, POST e DELETE autenticados.
 * Segurança: a chave vai no cabeçalho "access_token" e nunca aparece em mensagens ou logs.
 *
 * Erros:
 * - sem resposta (internet fora, demorou demais) ou erro 5xx do Asaas -> ProvedorIndisponivel
 * - erro 4xx (dado inválido, chave errada...) -> ErroAsaas, com a descrição que o Asaas devolveu
 */
class ClienteAsaas
{
    public function __construct(
        private readonly string $url,
        private readonly ?string $chave,
        private readonly int $timeout = 15,
    ) {}

    public static function daConfiguracao(): self
    {
        $config = config('mary.pagamento.asaas');

        return new self($config['url'], $config['chave'], (int) $config['timeout']);
    }

    // O ambiente de testes do Asaas tem "sandbox" no endereço. Simular pagamento só é permitido nele.
    public function ehSandbox(): bool
    {
        return str_contains($this->url, 'sandbox');
    }

    public function get(string $caminho, array $consulta = []): array
    {
        return $this->enviar(fn (PendingRequest $http) => $http->get($caminho, $consulta));
    }

    public function post(string $caminho, array $dados = []): array
    {
        return $this->enviar(fn (PendingRequest $http) => $http->post($caminho, $dados));
    }

    public function delete(string $caminho): array
    {
        return $this->enviar(fn (PendingRequest $http) => $http->delete($caminho));
    }

    private function enviar(callable $chamada): array
    {
        if (! $this->chave) {
            throw new ErroAsaas('A chave da API do Asaas (ASAAS_API_KEY) não está configurada no .env.', 0);
        }

        try {
            /** @var Response $resposta */
            $resposta = $chamada(Http::baseUrl($this->url)
                ->withHeaders(['access_token' => $this->chave, 'User-Agent' => 'MaryModas-TCC'])
                ->acceptJson()
                ->timeout($this->timeout));
        } catch (ConnectionException) {
            throw new ProvedorIndisponivel('O Asaas não respondeu (sem internet ou tempo esgotado).');
        }

        if ($resposta->serverError()) {
            throw new ProvedorIndisponivel("O Asaas está com instabilidade (erro {$resposta->status()}).");
        }

        if ($resposta->failed()) {
            // O Asaas devolve {"errors":[{"code":"...","description":"..."}]}
            $erro = $resposta->json('errors.0') ?? [];
            $descricao = $erro['description'] ?? ($resposta->status() === 401 ? 'Chave da API inválida.' : 'Erro desconhecido.');
            throw new ErroAsaas($descricao, $resposta->status(), $erro['code'] ?? null);
        }

        return $resposta->json() ?? [];
    }
}
