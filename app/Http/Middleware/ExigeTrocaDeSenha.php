<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda em todas as páginas: quem está com senha provisória só consegue abrir
 * a tela de criar a nova senha (e sair).
 */
class ExigeTrocaDeSenha
{
    private const LIBERADAS = ['senha-provisoria.edit', 'senha-provisoria.update', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->senha_provisoria && ! $request->routeIs(self::LIBERADAS)) {
            return redirect()->route('senha-provisoria.edit');
        }

        return $next($request);
    }
}
