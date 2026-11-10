<?php

namespace Tests\Quebra;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bateria 3: dados malucos em todos os formulários e pesquisas. Nada pode virar erro 500.
 */
class FuzzingTest extends TestCase
{
    use Cenario, RefreshDatabase;

    // Todos os nomes de campo usados nos formulários do sistema
    private const CAMPOS = [
        'name', 'email', 'cpf', 'telefone', 'password', 'password_confirmation', 'cep', 'rua', 'numero', 'complemento',
        'bairro', 'cidade', 'uf', 'nome', 'descricao', 'tipo_pessoa', 'documento', 'status', 'categoria_id', 'fornecedor_id',
        'tamanho', 'cor', 'preco', 'estoque', 'remover_imagem', 'codigo', 'tipo', 'valor', 'percentual', 'data_inicio', 'data_fim',
        'modo', 'categorias', 'quantidade', 'produto_id', 'cupom', 'forma', 'cartao_numero', 'cartao_nome', 'cartao_validade',
        'cartao_cvv', 'motivo', 'senha_atual', 'senha_encerrar', 'perfil', 'token', 'remember', 'aplica_em', 'desconto',
    ];

    public static function variantes(): array
    {
        return [
            'lista' => ['lista'], 'lista_aninhada' => ['lista_aninhada'], 'gigante' => ['gigante'], 'xss' => ['xss'],
            'sql' => ['sql'], 'emoji' => ['emoji'], 'numero_absurdo' => ['numero_absurdo'], 'negativo' => ['negativo'],
            'byte_nulo' => ['byte_nulo'], 'vazio' => ['vazio'], 'data_maluca' => ['data_maluca'],
        ];
    }

    private function valor(string $variante): mixed
    {
        return match ($variante) {
            'lista' => ['a', 'b'],
            'lista_aninhada' => ['x' => ['y' => 'z']],
            'gigante' => str_repeat('A', 70000),
            'xss' => '<script>alert(1)</script>"\'><img src=x onerror=alert(2)>',
            'sql' => "' OR 1=1; DROP TABLE users; --",
            'emoji' => '😀🧵 Ação Ñandú 中文 ‮',
            'numero_absurdo' => '999999999999999999999999999999',
            'negativo' => '-1',
            'byte_nulo' => "abc\0def",
            'vazio' => '',
            'data_maluca' => '2026-02-31',
        };
    }

    // Persona com acesso a cada grupo de rota (para passar da autenticação e chegar na validação)
    private function quem(string $uri): ?string
    {
        return match (true) {
            str_starts_with($uri, '/painel') => 'admin',
            in_array($uri, ['/entrar', '/criar-conta', '/esqueci-senha', '/redefinir-senha'], true) => null,
            $uri === '/criar-nova-senha' => 'provisoria',
            default => 'cliente_b',
        };
    }

    #[DataProvider('variantes')]
    public function test_formularios_nao_dao_500(string $variante): void
    {
        $this->montarCenario();
        $erros = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->getActionName(), 'App\\') || ! ($uri = $this->urlDe($rota))) {
                continue;
            }
            foreach (array_diff($rota->methods(), ['HEAD']) as $metodo) {
                $dados = array_fill_keys(self::CAMPOS, $this->valor($variante));
                $this->flushSession();
                app('auth')->forgetGuards();
                if ($quem = $this->quem($uri)) {
                    $this->actingAs($this->u[$quem]->fresh());
                }
                $resposta = $metodo === 'GET'
                    ? $this->call('GET', $uri, $dados)   // na URL: ?busca=...&page=...
                    : $this->call($metodo, $uri, $dados + ['imagem' => UploadedFile::fake()->create('x.php', 1, 'text/x-php')]);

                if ($resposta->getStatusCode() >= 500) {
                    $e = $resposta->exception;
                    $erros[] = "{$metodo} {$uri}: ".($e ? class_basename($e).' - '.mb_substr($e->getMessage(), 0, 160).' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine() : $resposta->getStatusCode());
                }
            }
        }

        $this->assertSame([], $erros, "\n".implode("\n", array_unique($erros)));
    }

    // Parâmetros de pesquisa e paginação malucos nas listas
    public function test_pesquisas_e_paginas_malucas(): void
    {
        $this->montarCenario();
        $erros = [];
        $consultas = ['busca' => ['a'], 'page' => '-5', 'status' => ['x'], 'categoria' => 'abc', 'perfil' => ['admin'],
            'inicio' => '31/02/2026', 'fim' => 'ontem', 'formato' => 'exe', 'ordem' => "'; --", 'uf' => ['PR']];
        $listas = ['/', '/painel/produtos', '/painel/categorias', '/painel/fornecedores', '/painel/promocoes', '/painel/pedidos',
            '/painel/usuarios', '/painel/relatorios/vendas', '/painel/relatorios/estoque', '/painel/relatorios/clientes', '/meus-pedidos'];
        foreach ($listas as $uri) {
            foreach ($consultas as $chave => $valor) {
                foreach (['page' => '99999999999999999999', 'busca' => '%_%', $chave => $valor] as $k => $v) {
                    $this->flushSession();
                    app('auth')->forgetGuards();
                    $this->actingAs($this->u[$uri === '/meus-pedidos' || $uri === '/' ? 'cliente' : 'admin']->fresh());
                    $r = $this->get($uri.'?'.http_build_query([$k => $v]));
                    if ($r->getStatusCode() >= 500) {
                        $e = $r->exception;
                        $erros[] = "{$uri}?{$k}=".json_encode($v).': '.($e ? class_basename($e).' - '.mb_substr($e->getMessage(), 0, 160).' @ '.str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine() : '');
                    }
                }
            }
        }
        $this->assertSame([], $erros, "\n".implode("\n", array_unique($erros)));
    }

    // IDs que não existem e IDs malucos na URL: 404, nunca 500
    public function test_ids_inexistentes_na_url(): void
    {
        $this->montarCenario();
        $erros = [];
        foreach (['999999', '0', '-1', 'abc', '1.5', '99999999999999999999999'] as $id) {
            foreach (['admin' => ['/painel/produtos/%s/editar', '/painel/pedidos/%s', '/painel/usuarios/%s/editar', '/painel/categorias/%s/editar'],
                'cliente' => ['/meus-pedidos/%s', '/meus-pedidos/%s/pagamento']] as $quem => $urls) {
                foreach ($urls as $u) {
                    $this->flushSession();
                    app('auth')->forgetGuards();
                    $r = $this->actingAs($this->u[$quem]->fresh())->get(sprintf($u, $id));
                    if ($r->getStatusCode() >= 500) {
                        $erros[] = sprintf($u, $id).' = '.$r->getStatusCode().' '.mb_substr((string) $r->exception?->getMessage(), 0, 150);
                    }
                }
            }
        }
        $this->assertSame([], $erros, "\n".implode("\n", $erros));
    }
}
