<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagamentoRequest;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Pagamentos\PagamentoException;
use App\Services\FinalizarCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Telas de pagamento do cliente (PIX, cartão e boleto).
 * As regras ficam no serviço FinalizarCompra; aqui só entram e saem as telas.
 */
class PagamentoController extends Controller
{
    public function __construct(private readonly FinalizarCompra $processo) {}

    public function create(Pedido $pedido): View|RedirectResponse
    {
        Gate::authorize('view', $pedido);

        if (! $pedido->aguardandoPagamento()) {
            return redirect()->route('loja.pedidos.show', $pedido)
                ->with('erro', "O pedido {$pedido->numero()} não está aguardando pagamento.");
        }

        $pedido->load(['itens', 'pagamentos']);

        return view('loja.pagamento.create', [
            'pedido' => $pedido,
            'pendente' => $pedido->pagamentos->firstWhere('status', Pagamento::PENDENTE),
        ]);
    }

    public function store(PagamentoRequest $request, Pedido $pedido): RedirectResponse
    {
        Gate::authorize('view', $pedido);

        try {
            $pagamento = $this->processo->pagar(
                $pedido,
                $request->user(),
                $request->validated('forma'),
                $request->only(['cartao_numero', 'cartao_nome', 'cartao_validade', 'cartao_cvv']),
            );
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage())->with('aba', $request->validated('forma'));
        }

        return $this->resultado($pedido, $pagamento);
    }

    // Botão do PIX simulado: faz o papel do aviso (webhook) que o banco mandaria
    public function simularPix(Pedido $pedido, Pagamento $pagamento): RedirectResponse
    {
        Gate::authorize('view', $pedido);
        abort_unless($pagamento->pedido_id === $pedido->id && $pagamento->forma === 'pix', 404);

        return $this->resultado($pedido, $this->processo->confirmarAprovacao($pagamento));
    }

    // Etapa 4 do passo a passo: "Concluído"
    public function concluido(Pedido $pedido): View|RedirectResponse
    {
        Gate::authorize('view', $pedido);

        $pagamento = $pedido->pagamentos()->where('status', Pagamento::APROVADO)->first();
        if (! $pagamento) {
            return redirect()->route('loja.pedidos.show', $pedido);
        }

        return view('loja.pagamento.concluido', compact('pedido', 'pagamento'));
    }

    // Etapa 6 do processo: mostra o resultado de acordo com o status da tentativa
    private function resultado(Pedido $pedido, Pagamento $pagamento): RedirectResponse
    {
        return match ($pagamento->status) {
            Pagamento::APROVADO => redirect()->route('loja.pagamento.concluido', $pedido),
            Pagamento::PENDENTE => redirect()->route('loja.pagamento.create', $pedido)->with('sucesso', $pagamento->forma === 'pix'
                ? 'PIX gerado! Pague com o código abaixo.'
                : 'Boleto gerado! O pedido será confirmado quando o pagamento compensar.'),
            Pagamento::ESTORNADO => redirect()->route('loja.pedidos.show', $pedido)
                ->with('erro', 'Não havia mais estoque no momento da aprovação: o pedido foi cancelado e o valor, estornado. Seu carrinho foi liberado para ajustes.'),
            default => redirect()->route('loja.pagamento.create', $pedido)
                ->with('aba', $pagamento->forma)
                ->with('erro', ($pagamento->mensagem ?: 'Pagamento recusado.').' Nada foi cobrado; você pode tentar de novo.'),
        };
    }
}
