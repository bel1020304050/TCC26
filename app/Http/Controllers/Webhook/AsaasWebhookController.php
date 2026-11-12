<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Pagamento;
use App\Pagamentos\PagamentoException;
use App\Services\FinalizarCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Aviso automático (webhook) do Asaas: "a cobrança pay_xxx mudou de status".
 *
 * Analogia: é o carteiro tocando a campainha. Antes de abrir, conferimos o crachá (token);
 * e não acreditamos no que ele fala — ligamos para o Asaas (consulta da cobrança) para confirmar.
 *
 * Observação: o Asaas precisa alcançar o site pela internet. No localhost isso não acontece;
 * por isso a tela de pagamento também faz a mesma consulta sozinha, a cada 5 segundos.
 */
class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, FinalizarCompra $processo): JsonResponse
    {
        // Segurança: o token configurado no painel do Asaas precisa bater com o do .env (comparação em tempo constante)
        $esperado = (string) config('mary.pagamento.asaas.webhook_token');
        $recebido = (string) $request->header('asaas-access-token');
        if ($esperado === '' || ! hash_equals($esperado, $recebido)) {
            return response()->json(['erro' => 'Token inválido.'], 401);
        }

        $referencia = $request->input('payment.id');
        $pagamento = is_string($referencia) ? Pagamento::where('referencia', $referencia)->first() : null;

        // Cobrança que não é nossa (ou já apagada): responde 200 para o Asaas não ficar reenviando
        if (! $pagamento) {
            return response()->json(['recebido' => true, 'acao' => 'ignorado']);
        }

        try {
            // Idempotente: o mesmo aviso repetido não baixa o estoque duas vezes
            $pagamento = $processo->verificar($pagamento);
        } catch (PagamentoException $e) {
            // Não conseguimos confirmar agora: 503 faz o Asaas tentar de novo mais tarde
            Log::warning("Webhook Asaas: {$e->getMessage()}");

            return response()->json(['erro' => 'Consulta indisponível.'], 503);
        }

        return response()->json(['recebido' => true, 'status' => $pagamento->status]);
    }
}
