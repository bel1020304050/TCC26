<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auditoria;
use App\Support\Email;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Confirmação do e-mail do cliente.
 * Regra: o cliente navega e monta o carrinho sem confirmar, mas só finaliza a compra
 * depois de clicar no link enviado por e-mail (middleware "verified" nas rotas do pedido).
 */
class VerificacaoEmailController extends Controller
{
    // Tela "Confirme seu e-mail" (para onde o cliente vai se tentar finalizar a compra sem confirmar)
    public function aviso(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('loja.carrinho');
        }

        return view('auth.verificar-email', ['email' => $request->user()->email]);
    }

    /**
     * Link do e-mail. Segurança: o EmailVerificationRequest confere a assinatura do link,
     * o prazo e se o link é da conta que está logada.
     */
    public function verificar(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
            Auditoria::registrar('confirmou_email', $request->user());
        }

        return redirect()->route('loja.carrinho')->with('sucesso', 'E-mail confirmado! Agora você já pode finalizar suas compras.');
    }

    public function reenviar(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('loja.carrinho');
        }

        if (! Email::enviar(fn () => $request->user()->sendEmailVerificationNotification())) {
            return back()->with('erro', Email::AVISO_FALHA);
        }

        return back()->with('sucesso', "Enviamos um novo link de confirmação para {$request->user()->email}.");
    }
}
