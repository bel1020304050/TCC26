<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Carrinho;
use App\Models\Pedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pedidos do cliente — stories 3.18 (criar) e 3.19 (consultar e acompanhar).
 */
class PedidoController extends Controller
{
    // 3.18 – Tela "Confirmar pedido": resumo do carrinho + endereço de entrega
    public function confirmar(Request $request): View|RedirectResponse
    {
        Gate::authorize('create', Pedido::class);

        $carrinho = Carrinho::doCliente($request->user())->load('itens.produto.categoria', 'pedido');

        if (! $carrinho->isAberto() && $carrinho->pedido) {
            return redirect()->route('loja.pedidos.show', $carrinho->pedido);
        }
        if ($carrinho->itens->isEmpty()) {
            return redirect()->route('loja.carrinho')->with('erro', 'Seu carrinho está vazio.');
        }

        return view('loja.pedidos.confirmar', [
            'carrinho' => $carrinho,
            'totais' => $carrinho->totais(),
            'problemas' => $carrinho->problemasDeEstoque(),
            'cliente' => $request->user(),
        ]);
    }

    // 3.18 – Criar o pedido. Tudo numa transação: ou cria tudo, ou nada.
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Pedido::class);
        $cliente = $request->user();

        return DB::transaction(function () use ($cliente) {
            // "Trava" o carrinho: um segundo clique espera o primeiro terminar (RNF.01 — só 1 pedido)
            $carrinho = Carrinho::where('user_id', $cliente->id)
                ->where('status', '!=', Carrinho::FINALIZADO)
                ->latest('id')->lockForUpdate()->first();

            if ($carrinho && ! $carrinho->isAberto() && $carrinho->pedido) {
                return redirect()->route('loja.pedidos.show', $carrinho->pedido)
                    ->with('sucesso', "O pedido {$carrinho->pedido->numero()} já foi criado.");
            }
            if (! $carrinho || $carrinho->itens()->doesntExist()) {
                return redirect()->route('loja.carrinho')->with('erro', 'Seu carrinho está vazio.');
            }

            // Produtos ativos e quantidades que cabem no estoque NESTE momento
            if ($problemas = $carrinho->problemasDeEstoque()) {
                return redirect()->route('loja.pedidos.confirmar')
                    ->withErrors(['estoque' => array_values($problemas)]);
            }

            if (! $cliente->cep || ! $cliente->rua || ! $cliente->numero || ! $cliente->cidade) {
                return redirect()->route('loja.pedidos.confirmar')
                    ->with('erro', 'Complete o seu endereço de entrega antes de confirmar o pedido.');
            }

            $totais = $carrinho->totais();

            $pedido = new Pedido;
            $pedido->user_id = $cliente->id;
            $pedido->carrinho_id = $carrinho->id;
            $pedido->subtotal = $totais['subtotal'];
            $pedido->desconto = $totais['desconto'];
            $pedido->total = $totais['total'];
            // Endereço copiado do cadastro (preserva o histórico se o cliente mudar depois)
            foreach (['cep', 'rua', 'numero', 'complemento', 'bairro', 'cidade', 'uf'] as $campo) {
                $pedido->{$campo} = $cliente->{$campo};
            }
            $pedido->save();

            // Itens "congelados": nome, tamanho, cor, quantidade e preço do momento da compra
            foreach ($carrinho->itens as $item) {
                $pedido->itens()->create([
                    'produto_id' => $item->produto_id,
                    'nome' => $item->produto->nome,
                    'tamanho' => $item->produto->tamanho,
                    'cor' => $item->produto->cor,
                    'quantidade' => $item->quantidade,
                    'preco_unitario' => $item->produto->preco,
                    'subtotal' => $item->subtotal(),
                ]);
            }

            $pedido->registrarStatus(Pedido::AGUARDANDO_PAGAMENTO, $cliente);

            // Carrinho vai para "Em Pagamento" e fica bloqueado. O estoque NÃO é baixado aqui (só no pagamento, 3.31).
            $carrinho->status = Carrinho::EM_PAGAMENTO;
            $carrinho->save();

            return redirect()->route('loja.pedidos.show', $pedido)
                ->with('sucesso', "Pedido {$pedido->numero()} criado! Ele aguarda o pagamento.");
        });
    }

    // 3.19 – Meus pedidos (do mais recente ao mais antigo)
    public function index(Request $request): View
    {
        $pedidos = Pedido::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(10);

        return view('loja.pedidos.index', compact('pedidos'));
    }

    // 3.19 – Detalhes: só o dono vê (verificação no servidor — RNF.01)
    public function show(Pedido $pedido): View
    {
        Gate::authorize('view', $pedido);

        $pedido->load(['itens', 'historico']);

        return view('loja.pedidos.show', compact('pedido'));
    }
}
