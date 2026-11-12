<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Produto;
use App\Models\Promocao;
use App\Support\Status;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Página de detalhes do produto (pública, como a vitrine).
 * Cada tamanho/cor é um produto próprio no banco: os botões de tamanho e cor levam à página da outra variação.
 */
class ProdutoController extends Controller
{
    // Regra: "Últimas X unidades!" só aparece com estoque baixo
    public const ESTOQUE_BAIXO = 5;

    public function show(Produto $produto): View
    {
        // Produto inativo ou de categoria inativa não tem página (como na vitrine). Sem estoque, a página abre com aviso.
        abort_unless($produto->status === Status::ATIVO && $produto->categoria?->status === Status::ATIVO, 404);

        $promocao = Promocao::melhorAutomatica($produto, Promocao::validas()->where('modo', Promocao::AUTOMATICA)->get());

        // Dica de cupom: o maior desconto entre os cupons válidos para este produto
        $cupom = Promocao::cuponsDisponiveis()
            ->filter(fn (Promocao $p) => $p->aplicaA($produto))
            ->sortByDesc(fn (Promocao $p) => $p->descontoSobre((float) $produto->preco))
            ->first();

        return view('loja.produto', [
            'produto' => $produto,
            'promocao' => $promocao,
            'cupom' => $cupom,
            'tamanhos' => $this->opcoes($produto, 'tamanho'),
            'cores' => $this->opcoes($produto, 'cor'),
        ]);
    }

    /**
     * Botões de tamanho (mesma cor) ou de cor (de preferência no mesmo tamanho).
     * Ex.: Vestido Floral M/Preto mostra tamanhos P, M, G em Preto e cores Preto, Azul.
     * Cada botão leva à página da variação correspondente; variação sem estoque aparece riscada.
     */
    private function opcoes(Produto $produto, string $campo): Collection
    {
        $outroCampo = $campo === 'tamanho' ? 'cor' : 'tamanho';

        $irmaos = Produto::query()
            ->where('nome', $produto->nome)->where('categoria_id', $produto->categoria_id)
            ->where('status', Status::ATIVO)
            ->orderBy('id')
            ->get(['id', 'tamanho', 'cor', 'estoque']);

        return $irmaos->groupBy($campo)
            ->map(fn (Collection $grupo) => $grupo->firstWhere($outroCampo, $produto->{$outroCampo}) ?? $grupo->sortByDesc('estoque')->first())
            // Tamanhos só da mesma cor (P/M/G de outra cor não fazem sentido aqui)
            ->filter(fn ($variacao) => $campo === 'cor' || $variacao->cor === $produto->cor)
            ->sortBy(fn ($v) => $campo === 'tamanho' ? self::ordemTamanho($v->tamanho) : $v->cor)
            ->values();
    }

    // PP, P, M, G, GG, XG na ordem certa; numeração (36, 38...) depois, em ordem crescente
    private static function ordemTamanho(string $tamanho): string
    {
        $ordem = array_flip(['PP', 'P', 'M', 'G', 'GG', 'XG', 'U']);

        return isset($ordem[$tamanho]) ? '0'.$ordem[$tamanho] : '1'.str_pad($tamanho, 4, '0', STR_PAD_LEFT);
    }
}
