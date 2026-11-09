<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Pagamentos\PagamentoException;
use App\Policies\PedidoPolicy;
use App\Services\AndamentoPedido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pedidos no painel: lista com pesquisa e filtros, detalhes, atualizar status e cancelar.
 */
class PedidoController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Pedido::class);

        $busca = trim((string) $request->query('busca'));
        $status = $request->query('status');
        $de = $this->data($request->query('de'));
        $ate = $this->data($request->query('ate'));

        $pedidos = Pedido::query()
            ->with('cliente:id,name,email,telefone,status,encerrada_em')
            // Pesquisa por nº do pedido (com ou sem #) ou por nome/e-mail do cliente
            ->when($busca !== '', function ($q) use ($busca) {
                $numero = ltrim($busca, '#');
                $q->where(function ($q) use ($busca, $numero) {
                    if (ctype_digit($numero)) {
                        $q->orWhere('id', (int) $numero);
                    }
                    $q->orWhereHas('cliente', fn ($c) => $c
                        ->where('name', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%"));
                });
            })
            ->when(array_key_exists((string) $status, Pedido::STATUS), fn ($q) => $q->where('status', $status))
            ->when($de, fn ($q) => $q->where('created_at', '>=', $de->startOfDay()))
            ->when($ate, fn ($q) => $q->where('created_at', '<=', $ate->endOfDay()))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('painel.pedidos.index', [
            'pedidos' => $pedidos,
            'busca' => $busca,
            'status' => $status,
            'de' => $de?->toDateString(),
            'ate' => $ate?->toDateString(),
        ]);
    }

    public function show(Pedido $pedido): View
    {
        Gate::authorize('view', $pedido);

        $pedido->load(['itens', 'historico.responsavel', 'cliente', 'pagamentos.confirmadoPor']);

        return view('painel.pedidos.show', compact('pedido'));
    }

    // Atualiza o status. Regra: só aceita o próximo passo do fluxo (sem pular nem voltar)
    public function atualizarStatus(Request $request, Pedido $pedido, AndamentoPedido $andamento): RedirectResponse
    {
        Gate::authorize('atualizarStatus', $pedido);
        $dados = $request->validate(['status' => ['required', 'string']], [], ['status' => 'novo status']);

        try {
            $andamento->atualizarStatus($pedido, $dados['status'], $request->user());
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', "Pedido {$pedido->numero()} atualizado para ".Pedido::STATUS[$dados['status']].'.');
    }

    // Cancela o pedido. Validação: para a equipe, o motivo é obrigatório
    public function cancelar(Request $request, Pedido $pedido, AndamentoPedido $andamento): RedirectResponse
    {
        if ($bloqueio = PedidoPolicy::motivoBloqueioCancelamento($request->user(), $pedido)) {
            return back()->with('erro', $bloqueio);
        }
        Gate::authorize('cancelar', $pedido);

        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:255']], [], ['motivo' => 'motivo']);
        $estava = $pedido->statusLabel();

        try {
            $andamento->cancelar($pedido, $request->user(), $dados['motivo']);
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', $estava === 'Aguardando Pagamento'
            ? "Pedido {$pedido->numero()} cancelado."
            : "Pedido {$pedido->numero()} cancelado: pagamento estornado e itens devolvidos ao estoque.");
    }

    // Data do filtro no formato do <input type="date"> (AAAA-MM-DD); inválida = sem filtro
    private function data(?string $valor): ?Carbon
    {
        if (! $valor || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
