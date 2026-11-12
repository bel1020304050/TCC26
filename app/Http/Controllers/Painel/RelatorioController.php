<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Pedido;
use App\Models\Produto;
use App\Models\User;
use App\Relatorios\Relatorio;
use App\Relatorios\RelatorioAuditoria;
use App\Relatorios\RelatorioClientes;
use App\Relatorios\RelatorioEstoque;
use App\Relatorios\RelatorioVendas;
use App\Support\Pdf;
use App\Support\TextoAuditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios de Vendas, Estoque e Clientes.
 * Cada um abre na tela e pode ser exportado em CSV (planilha) ou PDF (gerado no servidor com o dompdf).
 * Os dois arquivos trazem TODOS os registros do filtro, não só a página que está na tela.
 */
class RelatorioController extends Controller
{
    public function index(): View
    {
        Gate::authorize('relatorio-estoque'); // qualquer pessoa da equipe vê a página inicial

        return view('painel.relatorios.index');
    }

    public function vendas(Request $request): Response|View
    {
        return $this->gerar($request, new RelatorioVendas($request), 'painel.relatorios.filtros-vendas', [
            'statusOpcoes' => Pedido::STATUS,
            'formas' => Pedido::FORMAS_PAGAMENTO,
        ]);
    }

    public function estoque(Request $request): Response|View
    {
        return $this->gerar($request, new RelatorioEstoque($request), 'painel.relatorios.filtros-estoque', [
            'categorias' => Categoria::orderBy('nome')->get(['id', 'nome']),
            'fornecedores' => Fornecedor::orderBy('nome')->get(['id', 'nome']),
            'tamanhos' => Produto::distinct()->orderBy('tamanho')->pluck('tamanho'),
            'cores' => Produto::distinct()->orderBy('cor')->pluck('cor'),
        ]);
    }

    public function clientes(Request $request): Response|View
    {
        return $this->gerar($request, new RelatorioClientes($request), 'painel.relatorios.filtros-clientes');
    }

    public function auditoria(Request $request): Response|View
    {
        return $this->gerar($request, new RelatorioAuditoria($request), 'painel.relatorios.filtros-auditoria', [
            'equipe' => User::equipe()->orderBy('name')->get(['id', 'name', 'perfil']),
            'acoes' => TextoAuditoria::ACOES,
            'areas' => array_unique(TextoAuditoria::AREAS),
        ]);
    }

    private function gerar(Request $request, Relatorio $relatorio, string $filtros, array $opcoes = []): Response|View
    {
        // Segurança: tanto a tela quanto a exportação recusam quem não tem o perfil certo
        Gate::authorize($relatorio->permissao());

        return match ($request->query('exportar')) {
            'csv' => $this->csv($relatorio),
            'pdf' => Pdf::baixar('painel.relatorios.pdf', ['relatorio' => $relatorio, 'registros' => $relatorio->registros()], $relatorio->nomeArquivo('pdf')),
            default => view('painel.relatorios.show', [
                'relatorio' => $relatorio,
                'registros' => $relatorio->paginar(),
                'filtrosView' => $filtros,
                'f' => $relatorio->filtros(),
            ] + $opcoes),
        };
    }

    // CSV com ";" e BOM UTF-8: abre direto no Excel em português, com acentos certos
    private function csv(Relatorio $relatorio): StreamedResponse
    {
        return response()->streamDownload(function () use ($relatorio) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, $relatorio->colunas(), ';');
            foreach ($relatorio->registros() as $registro) {
                fputcsv($saida, array_map([self::class, 'celulaSegura'], $relatorio->linha($registro)), ';');
            }
            fclose($saida);
        }, $relatorio->nomeArquivo('csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Segurança (CSV injection): o Excel trata como fórmula toda célula que começa com = + - @ (ou tab/enter).
     * Um cliente poderia se cadastrar com o nome "=HYPERLINK(...)" e a fórmula rodaria no computador da loja.
     * Um apóstrofo na frente faz o Excel mostrar o texto como texto (recomendação da OWASP).
     * Números negativos ("-10,50") continuam números.
     */
    private static function celulaSegura(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '' || is_numeric(str_replace(['.', ','], ['', '.'], $valor))) {
            return $valor;
        }

        return in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$valor : $valor;
    }
}
