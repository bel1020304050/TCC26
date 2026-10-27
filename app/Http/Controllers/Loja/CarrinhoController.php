<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Carrinho;
use App\Models\CarrinhoItem;
use App\Models\Produto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carrinho — story 3.30 (funcionalidade de apoio).
 * Toda conta (preço, subtotal, total) e toda checagem de estoque é feita aqui no servidor.
 */
class CarrinhoController extends Controller
{
    public function index(Request $request): View
    {
        $carrinho = Carrinho::doCliente($request->user())->load('itens.produto.categoria', 'pedido');

        return view('loja.carrinho', [
            'carrinho' => $carrinho,
            'totais' => $carrinho->totais(),
            'problemas' => $carrinho->problemasDeEstoque(),
        ]);
    }

    // Adicionar a partir da vitrine (quantidade 1; se já estiver no carrinho, soma 1)
    public function adicionar(Request $request): RedirectResponse
    {
        $dados = $request->validate(['produto_id' => ['required', 'integer']]);

        $carrinho = Carrinho::doCliente($request->user());
        if ($bloqueio = $this->bloqueado($carrinho)) {
            return $bloqueio;
        }

        // Só produtos que estão na vitrine: ativos, com estoque e de categoria ativa
        $produto = Produto::naVitrine()->find($dados['produto_id']);
        if (! $produto) {
            return back()->with('erro', 'Este produto não está disponível para compra.');
        }

        $item = $carrinho->itens()->firstOrNew(['produto_id' => $produto->id]);
        $novaQuantidade = ($item->quantidade ?? 0) + 1;

        if ($novaQuantidade > $produto->estoque) {
            return back()->with('erro', "Só há {$produto->estoque} unidade(s) disponível(is) de {$produto->nome} ({$produto->variacao()}), e todas já estão no seu carrinho.");
        }

        $item->quantidade = $novaQuantidade;
        $item->save();

        return back()->with('sucesso', "{$produto->nome} ({$produto->variacao()}) adicionado ao carrinho.");
    }

    // Aumentar ou diminuir (botões − e +)
    public function atualizar(Request $request, CarrinhoItem $item): RedirectResponse
    {
        $carrinho = $this->carrinhoDoItem($request, $item);
        if ($bloqueio = $this->bloqueado($carrinho)) {
            return $bloqueio;
        }

        $quantidade = (int) $request->validate(['quantidade' => ['required', 'integer', 'min:1', 'max:999']])['quantidade'];
        $produto = $item->produto;

        if ($quantidade > $produto->estoque) {
            return back()->with('erro', "Quantidade indisponível: só há {$produto->estoque} unidade(s) de {$produto->nome} ({$produto->variacao()}).");
        }

        $item->quantidade = $quantidade;
        $item->save();

        return back();
    }

    public function remover(Request $request, CarrinhoItem $item): RedirectResponse
    {
        $carrinho = $this->carrinhoDoItem($request, $item);
        if ($bloqueio = $this->bloqueado($carrinho)) {
            return $bloqueio;
        }

        $item->delete();

        return back()->with('sucesso', "{$item->produto->nome} ({$item->produto->variacao()}) removido do carrinho.");
    }

    // O item precisa ser do carrinho do próprio cliente (cada carrinho pertence a um único cliente)
    private function carrinhoDoItem(Request $request, CarrinhoItem $item): Carrinho
    {
        abort_unless($item->carrinho->user_id === $request->user()->id, 403);

        return $item->carrinho;
    }

    // Carrinho "Em Pagamento" fica bloqueado para alterações (3.18)
    private function bloqueado(Carrinho $carrinho): ?RedirectResponse
    {
        if ($carrinho->isAberto()) {
            return null;
        }

        return redirect()->route('loja.carrinho')
            ->with('erro', 'Seu carrinho está bloqueado porque já gerou um pedido aguardando pagamento.');
    }
}
