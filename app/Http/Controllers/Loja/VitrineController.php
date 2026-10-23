<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Story 3.2 – Visualizar e Pesquisar Produtos (vitrine pública, sem login).
 */
class VitrineController extends Controller
{
    private const POR_PAGINA = 20; // RNF.01 da 3.2

    private const ORDENACOES = [
        'recentes' => ['created_at', 'desc'],
        'menor_preco' => ['preco', 'asc'],
        'maior_preco' => ['preco', 'desc'],
    ];

    public function __invoke(Request $request): View
    {
        $filtros = [
            'busca' => trim((string) $request->query('busca')),
            'categoria' => $request->integer('categoria') ?: null,
            'tamanho' => (string) $request->query('tamanho'),
            'cor' => (string) $request->query('cor'),
            'ordem' => array_key_exists($request->query('ordem'), self::ORDENACOES) ? $request->query('ordem') : 'recentes',
        ];

        [$coluna, $direcao] = self::ORDENACOES[$filtros['ordem']];

        $produtos = Produto::query()
            ->naVitrine()
            ->with('categoria:id,nome')
            // LIKE encontra parte do nome; no MySQL/MariaDB ignora maiúsculas/minúsculas
            ->when($filtros['busca'] !== '', fn ($q) => $q->where('nome', 'like', '%'.$filtros['busca'].'%'))
            ->when($filtros['categoria'], fn ($q) => $q->where('categoria_id', $filtros['categoria']))
            ->when($filtros['tamanho'] !== '', fn ($q) => $q->where('tamanho', $filtros['tamanho']))
            ->when($filtros['cor'] !== '', fn ($q) => $q->where('cor', $filtros['cor']))
            ->orderBy($coluna, $direcao)
            ->orderBy('id', 'desc')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        // Opções dos filtros: só o que existe na vitrine (categorias ativas)
        $categorias = Categoria::ativos()->orderBy('nome')->get(['id', 'nome']);
        $tamanhos = Produto::naVitrine()->distinct()->orderBy('tamanho')->pluck('tamanho');
        $cores = Produto::naVitrine()->distinct()->orderBy('cor')->pluck('cor');

        return view('loja.vitrine', compact('produtos', 'filtros', 'categorias', 'tamanhos', 'cores'));
    }
}
