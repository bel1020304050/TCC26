<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Carrinho;
use App\Models\CarrinhoItem;
use App\Models\Produto;
use App\Models\Promocao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carrinho do cliente: adicionar, alterar quantidade, remover itens e aplicar cupom.
 * Toda conta (preço, subtotal, total) e toda checagem de estoque é feita aqui no servidor.
 */
class CarrinhoController extends Controller
{
    public function index(Request $request): View
    {
        $carrinho = Carrinho::doCliente($request->user())->load('itens.produto.categoria', 'pedido');

        // Regra: o cupom é conferido de novo a cada visita. Se foi inativado ou expirou, sai do carrinho com aviso
        if ($carrinho->isAberto() && $carrinho->cupom && ! Promocao::cupomValido($carrinho->cupom)) {
            $codigo = $carrinho->cupom;
            $carrinho->cupom = null;
            $carrinho->save();
            session()->now('erro', "O cupom {$codigo} não está mais disponível e foi removido do seu carrinho.");
        }

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

    // Validação: o cupom só é aceito se estiver Ativo e dentro do período (Vigente)
    public function aplicarCupom(Request $request): RedirectResponse
    {
        $dados = $request->validate(['cupom' => ['required', 'string', 'max:30']], [], ['cupom' => 'cupom']);

        $carrinho = Carrinho::doCliente($request->user());
        if ($bloqueio = $this->bloqueado($carrinho)) {
            return $bloqueio;
        }

        $codigo = mb_strtoupper(trim($dados['cupom']));
        $promocao = Promocao::cupomValido($codigo);
        if (! $promocao) {
            return back()->withInput()->with('erro', "O cupom {$codigo} não está disponível.");
        }

        $carrinho->cupom = $promocao->codigo;
        $carrinho->save();

        $totais = $carrinho->totais();

        return back()->with('sucesso', match (true) {
            $totais['promocao']?->is($promocao) => "Cupom aplicado: {$promocao->nome} ({$promocao->descontoLabel()}).",
            $totais['promocao'] !== null => "Cupom {$promocao->codigo} válido, mas a promoção {$totais['promocao']->nome} dá um desconto maior. Só o maior desconto é aplicado.",
            default => "Cupom {$promocao->codigo} válido, mas não se aplica aos itens do seu carrinho.",
        });
    }

    public function removerCupom(Request $request): RedirectResponse
    {
        $carrinho = Carrinho::doCliente($request->user());
        if ($bloqueio = $this->bloqueado($carrinho)) {
            return $bloqueio;
        }

        $carrinho->cupom = null;
        $carrinho->save();

        return back()->with('sucesso', 'Cupom removido.');
    }

    // O item precisa ser do carrinho do próprio cliente (cada carrinho pertence a um único cliente)
    private function carrinhoDoItem(Request $request, CarrinhoItem $item): Carrinho
    {
        abort_unless($item->carrinho->user_id === $request->user()->id, 403);

        return $item->carrinho;
    }

    // Regra: depois de virar pedido, o carrinho fica "Em Pagamento" e não pode ser alterado
    private function bloqueado(Carrinho $carrinho): ?RedirectResponse
    {
        if ($carrinho->isAberto()) {
            return null;
        }

        return redirect()->route('loja.carrinho')
            ->with('erro', 'Seu carrinho está bloqueado porque já gerou um pedido aguardando pagamento.');
    }
}
