<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Produto;
use App\Models\Promocao;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vitrine pública (não precisa de login): lista, pesquisa, filtros e ordenação dos produtos.
 */
class VitrineController extends Controller
{
    private const POR_PAGINA = 20; // 20 produtos por página, para a vitrine carregar rápido

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

        // Regra: o selo e o preço promocional usam só promoções AUTOMÁTICAS, Ativas e Vigentes.
        // Cupom não aparece na vitrine, porque depende do código digitado no carrinho.
        $automaticas = Promocao::validas()->where('modo', Promocao::AUTOMATICA)->get();
        $promocoes = $produtos->getCollection()->mapWithKeys(fn (Produto $produto) => [
            $produto->id => Promocao::melhorAutomatica($produto, $automaticas),
        ]);

        return view('loja.vitrine', compact('produtos', 'filtros', 'categorias', 'tamanhos', 'cores', 'promocoes'));
    }
}
