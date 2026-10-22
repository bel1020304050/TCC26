<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se a conta for inativada (ou encerrada) enquanto o usuário está logado,
 * a sessão é encerrada na próxima requisição (RNF.01 da story 3.8).
 */
class EncerraSessaoInativa
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && ! $usuario->isAtivo()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Sua conta não está ativa. Fale com a loja se precisar de ajuda.']);
        }

        return $next($request);
    }
}
