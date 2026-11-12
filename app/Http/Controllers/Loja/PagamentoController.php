<?php

namespace App\Http\Controllers\Loja;

use App\Http\Controllers\Controller;
use App\Http\Requests\PagamentoRequest;
use App\Models\Pagamento;
use App\Models\Pedido;
use App\Pagamentos\PagamentoException;
use App\Services\FinalizarCompra;
use Illuminate\Http\JsonResponse;
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
            'asaas' => config('mary.pagamento.provedor') === 'asaas',
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

    // Botão "Simular pagamento recebido" do PIX: no simulado aprova direto; no Asaas, pede ao sandbox para confirmar
    public function simularPix(Pedido $pedido, Pagamento $pagamento): RedirectResponse
    {
        Gate::authorize('view', $pedido);
        abort_unless($pagamento->pedido_id === $pedido->id && $pagamento->forma === 'pix', 404);

        try {
            $pagamento = $this->processo->simular($pagamento);
        } catch (PagamentoException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return $this->resultado($pedido, $pagamento);
    }

    /**
     * Consulta automática: a tela de pagamento chama esta rota a cada 5 segundos enquanto a cobrança está pendente
     * (no localhost o aviso/webhook do Asaas não chega). Responde em JSON: o status e, se mudou, para onde ir.
     */
    public function status(Pedido $pedido, Pagamento $pagamento): JsonResponse
    {
        Gate::authorize('view', $pedido);
        abort_unless($pagamento->pedido_id === $pedido->id, 404);

        try {
            $pagamento = $this->processo->verificar($pagamento);
        } catch (PagamentoException) {
            // Asaas fora do ar por um instante: a tela continua esperando e pergunta de novo
            return response()->json(['status' => Pagamento::PENDENTE]);
        }

        if ($pagamento->isPendente()) {
            return response()->json(['status' => Pagamento::PENDENTE]);
        }

        // Mudou: devolve o endereço da tela de resultado (a mensagem já fica guardada para a próxima página)
        return response()->json(['status' => $pagamento->status, 'ir_para' => $this->resultado($pedido, $pagamento)->getTargetUrl()]);
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
            Pagamento::PENDENTE => redirect()->route('loja.pagamento.create', $pedido)->with('sucesso', match ($pagamento->forma) {
                    'pix' => 'PIX gerado! Pague com o código abaixo.',
                    'cartao' => 'Cobrança criada! Clique em "Pagar com cartão no Asaas" para digitar o cartão na página segura.',
                    default => 'Boleto gerado! O pedido será confirmado quando o pagamento compensar.',
                }),
            Pagamento::ESTORNADO => redirect()->route('loja.pedidos.show', $pedido)
                ->with('erro', 'Não havia mais estoque no momento da aprovação: o pedido foi cancelado e o valor, estornado. Seu carrinho foi liberado para ajustes.'),
            default => redirect()->route('loja.pagamento.create', $pedido)
                ->with('aba', $pagamento->forma)
                ->with('erro', ($pagamento->mensagem ?: 'Pagamento recusado.').' Nada foi cobrado; você pode tentar de novo.'),
        };
    }
}
