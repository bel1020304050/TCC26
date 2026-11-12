<?php

namespace App\Http\Controllers\Painel;

use App\Http\Controllers\Controller;
use App\Models\Pagamento;
use App\Pagamentos\PagamentoException;
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

        // A auditoria ("pagamento aprovado" por quem confirmou) é registrada dentro do processo.
        // No Asaas (sandbox), a confirmação é enviada para lá e o status volta pela consulta.
        try {
            $pagamento = $processo->simular($pagamento, $request->user());
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $pedido = $pagamento->pedido;

        return back()->with(
            $pagamento->status === Pagamento::APROVADO ? 'sucesso' : 'erro',
            match ($pagamento->status) {
                Pagamento::APROVADO => "Boleto compensado: o pedido {$pedido->numero()} agora está Pago e o estoque foi baixado.",
                Pagamento::PENDENTE => 'O Asaas ainda não confirmou o boleto. Tente de novo em alguns segundos.',
                default => "Não foi possível aprovar: {$pagamento->mensagem}",
            }
        );
    }
}
