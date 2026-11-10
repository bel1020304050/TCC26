<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segurança: páginas de quem está logado não ficam guardadas no navegador.
 * Sem isso, num computador compartilhado, alguém poderia sair do site e a próxima pessoa
 * apertar "Voltar" e ver a cópia da página "Minha conta" (com CPF e endereço).
 */
class SemCacheParaLogado
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        if ($request->user()) {
            $resposta->headers->set('Cache-Control', 'no-store, private');
        }

        return $resposta;
    }
}
