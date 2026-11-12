<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Segurança: nenhum formulário do sistema envia listas (campo[]=...). Se alguém manipular a requisição
 * para mandar "email[]=x" ou "?busca[]=x", o valor vira vazio antes de chegar ao código.
 * Assim a validação responde normalmente ("campo obrigatório") em vez de dar erro 500,
 * e as pesquisas simplesmente ignoram o filtro.
 * Arquivos enviados (imagem do produto) não passam por aqui.
 */
class RecusaCamposEmLista
{
    public function handle(Request $request, Closure $next): Response
    {
        // Exceção: os avisos (webhooks) chegam em JSON com objetos ({"payment": {...}}), não de um formulário
        if ($request->is('webhooks/*')) {
            return $next($request);
        }

        foreach ([$request->query, $request->request] as $origem) {
            foreach ($origem->all() as $campo => $valor) {
                if (is_array($valor)) {
                    $origem->set($campo, null);
                }
            }
        }

        return $next($request);
    }
}
