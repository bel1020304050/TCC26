<?php

namespace Tests\Quebra;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Bateria 1: todas as rotas, com cada tipo de usuário, com corpo vazio.
 * Nenhuma combinação pode dar erro 500. As permissões de cada rota são conferidas nos testes de Feature e em SegurancaTest.
 */
class VarreduraRotasTest extends TestCase
{
    use Cenario, RefreshDatabase;

    public static function tipos(): array
    {
        return array_map(fn ($t) => [$t], ['visitante' => 'visitante', 'cliente' => 'cliente', 'nao_verificado' => 'nao_verificado',
            'atendente' => 'atendente', 'gerente' => 'gerente', 'admin' => 'admin', 'provisoria' => 'provisoria']);
    }

    #[DataProvider('tipos')]
    public function test_nenhuma_rota_da_erro_500(string $tipo): void
    {
        $this->montarCenario();
        $erros = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->getActionName(), 'App\\')) {
                continue; // rotas internas do Laravel
            }
            $uri = $this->urlDe($rota);
            if (! $uri) {
                $erros[] = "sem parâmetro para {$rota->uri()}";
                continue;
            }
            foreach (array_diff($rota->methods(), ['HEAD']) as $metodo) {
                // Sessão e login limpos a cada requisição
                $this->flushSession();
                app('auth')->forgetGuards();
                if ($tipo !== 'visitante') {
                    $this->actingAs($this->u[$tipo]->fresh());
                }
                $resposta = $this->call($metodo, $uri);
                $status = $resposta->getStatusCode();
                if ($status >= 500) {
                    $erros[] = "{$metodo} {$uri} = {$status}: ".mb_substr(strip_tags((string) ($resposta->exception?->getMessage() ?? '')), 0, 200);
                }
            }
        }

        $this->assertSame([], $erros, implode("\n", $erros));
    }
}
