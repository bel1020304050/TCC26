<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Pagamento;
use App\Services\FinalizarCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Painel: o Administrador confirma que o boleto foi pago (faz o papel do aviso que o banco mandaria).
 */
class PagamentoController extends Controller
{
    public function confirmarBoleto(Request $request, Pagamento $pagamento, FinalizarCompra $processo): RedirectResponse
    {
        Gate::authorize('confirmarBoleto', $pagamento);

        // A auditoria ("pagamento aprovado" por quem confirmou) é registrada dentro do processo
        $pagamento = $processo->confirmarAprovacao($pagamento, $request->user());

        $pedido = $pagamento->pedido;

        return back()->with(
            $pagamento->status === Pagamento::APROVADO ? 'sucesso' : 'erro',
            $pagamento->status === Pagamento::APROVADO
                ? "Boleto compensado: o pedido {$pedido->numero()} agora está Pago e o estoque foi baixado."
                : "Não foi possível aprovar: {$pagamento->mensagem}"
        );
    }
}
