<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Pedidos na área administrativa — story 3.19 (consulta com pesquisa e filtros).
 * Atualizar status (3.20) e cancelar (3.21) chegam na Fase 7.
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
            ->with('cliente:id,name,email')
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

        $pedido->load(['itens', 'historico.responsavel', 'cliente']);

        return view('painel.pedidos.show', compact('pedido'));
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
