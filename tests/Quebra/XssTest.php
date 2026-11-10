<?php

namespace Tests\Quebra;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Models\Promocao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bateria 3 (XSS): textos com código salvos no banco precisam aparecer escapados em todas as telas e no CSV.
 */
class XssTest extends TestCase
{
    use Cenario, RefreshDatabase;

    private const ATAQUE = '<script>alert(1)</script>';

    public function test_codigo_salvo_nunca_e_executado_nas_telas(): void
    {
        $this->montarCenario();
        $a = self::ATAQUE;
        $this->m['categoria']->update(['nome' => "Cat {$a}", 'descricao' => $a]);
        $this->m['fornecedor']->update(['nome' => "Forn {$a}", 'rua' => $a, 'cidade' => $a]);
        $this->m['produto']->update(['nome' => "Prod {$a}", 'descricao' => $a, 'cor' => $a]);
        $this->m['promocao']->update(['nome' => "Promo {$a}"]);
        foreach (['cliente', 'atendente', 'gerente'] as $t) {
            $this->u[$t]->update(['name' => "Nome {$a}", 'rua' => $a, 'bairro' => $a]);
        }
        $this->m['pendente']->forceFill(['rua' => $a, 'complemento' => $a])->save();
        $this->m['pendente']->historico()->create(['status' => 'cancelado', 'observacao' => "Motivo: {$a}", 'user_id' => $this->u['admin']->id]);

        $paginas = [
            'cliente' => ['/', '/carrinho', '/meus-pedidos', '/meus-pedidos/'.$this->m['pendente']->id, '/minha-conta', '/minha-conta/editar', '/?busca='.urlencode($a)],
            'admin' => ['/painel', '/painel/produtos', '/painel/produtos/'.$this->m['produto']->id.'/editar', '/painel/categorias', '/painel/fornecedores',
                '/painel/fornecedores/'.$this->m['fornecedor']->id.'/editar', '/painel/promocoes', '/painel/pedidos', '/painel/pedidos/'.$this->m['pendente']->id,
                '/painel/usuarios', '/painel/relatorios/vendas', '/painel/relatorios/clientes', '/painel/relatorios/estoque',
                '/painel/produtos?busca='.urlencode($a), '/painel/relatorios/vendas?exportar=pdf'],
        ];
        $vazou = [];
        foreach ($paginas as $quem => $urls) {
            foreach ($urls as $url) {
                $html = $this->actingAs($this->u[$quem]->fresh())->get($url)->getContent();
                if (str_contains($html, $a)) {
                    $vazou[] = $url;
                }
            }
        }
        $this->assertSame([], $vazou, 'Páginas com <script> sem escapar: '.implode(', ', $vazou));
    }

    // CSV: texto começando com "=" vira fórmula no Excel (CSV injection)
    public function test_csv_nao_exporta_formula(): void
    {
        $this->montarCenario();
        $this->m['produto']->update(['nome' => '=HYPERLINK("http://malicioso.com","Clique")']);
        $this->u['cliente']->update(['name' => '@SUM(1+1)*cmd|" /C calc"!A0']);
        $saidas = [];
        foreach (['estoque', 'clientes', 'vendas'] as $rel) {
            $csv = $this->actingAs($this->u['admin'])->get("/painel/relatorios/{$rel}?exportar=csv")->streamedContent();
            foreach (explode("\n", $csv) as $linha) {
                foreach (str_getcsv($linha, ';') as $celula) {
                    if ((string) $celula !== '' && in_array($celula[0], ['=', '+', '-', '@'], true) && ! is_numeric($celula)) {
                        $saidas[] = "{$rel}: {$celula}";
                    }
                }
            }
        }
        $this->assertSame([], $saidas, "\nCélulas que o Excel trataria como fórmula:\n".implode("\n", $saidas));
    }
}
