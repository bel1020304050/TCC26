<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Models\Carrinho;
use App\Models\Pedido;
use App\Support\Auditoria;
use App\Pagamentos\PagamentoException;
use App\Policies\PedidoPolicy;
use App\Services\AndamentoPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pedidos do lado do cliente: confirmar o carrinho, criar o pedido, acompanhar e cancelar.
 */
class PedidoController extends Controller
{
    // Tela "Confirmar pedido": resumo do carrinho e endereço de entrega
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

    // Cria o pedido. Banco: tudo numa transação; se algo falhar no meio, nada é gravado.
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Pedido::class);
        $cliente = $request->user();

        return DB::transaction(function () use ($cliente) {
            // Banco: lockForUpdate() trava a linha do carrinho até o fim da transação.
            // Se o cliente clicar duas vezes em "Confirmar", o segundo clique espera o primeiro
            // terminar, encontra o carrinho já fechado e não cria um pedido repetido.
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
            // Promoção usada (o valor do desconto fica congelado; editar a promoção depois não muda o pedido)
            $pedido->promocao_id = $totais['promocao']?->id;
            $pedido->cupom = $totais['promocao']?->codigo;
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

            // Regra: o carrinho fica "Em Pagamento" (bloqueado). O estoque NÃO baixa aqui, só quando o pagamento for aprovado.
            $carrinho->status = Carrinho::EM_PAGAMENTO;
            $carrinho->save();

            Auditoria::registrar('criou', $pedido, ['total' => (float) $pedido->total]);

            // Leva o cliente direto para a tela de pagamento deste pedido
            return redirect()->route('loja.pagamento.create', $pedido)
                ->with('sucesso', "Pedido {$pedido->numero()} criado! Escolha a forma de pagamento.");
        });
    }

    // "Meus pedidos", do mais recente ao mais antigo
    public function index(Request $request): View
    {
        $pedidos = Pedido::where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(10);

        return view('loja.pedidos.index', compact('pedidos'));
    }

    // Detalhes do pedido. Segurança: a Policy confere no servidor se o pedido é de quem está logado
    public function show(Pedido $pedido): View
    {
        Gate::authorize('view', $pedido);

        $pedido->load(['itens', 'historico', 'pagamentos']);

        return view('loja.pedidos.show', compact('pedido'));
    }

    // Regra: o cliente só cancela o próprio pedido enquanto ele estiver Aguardando Pagamento (motivo opcional)
    public function cancelar(Request $request, Pedido $pedido, AndamentoPedido $andamento): RedirectResponse
    {
        Gate::authorize('view', $pedido); // pedido de outro cliente: acesso negado

        if ($bloqueio = PedidoPolicy::motivoBloqueioCancelamento($request->user(), $pedido)) {
            return back()->with('erro', $bloqueio);
        }
        if (! $request->user()->can('cancelar', $pedido)) {
            return back()->with('erro', 'Este pedido já foi pago. Para cancelar, entre em contato com a loja.');
        }

        $request->validate(['motivo' => ['nullable', 'string', 'max:255']]);
        $motivo = trim((string) $request->input('motivo')) ?: null; // opcional para o cliente

        try {
            $andamento->cancelar($pedido, $request->user(), $motivo);
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('loja.pedidos.show', $pedido)
            ->with('sucesso', "Pedido {$pedido->numero()} cancelado. Seu carrinho voltou a ficar disponível para alterações.");
    }
}
