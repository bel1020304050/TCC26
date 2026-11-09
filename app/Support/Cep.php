<?php

namespace App\Support;

/**
 * Padroniza e valida o CEP nos formulários (cliente e fornecedor).
 * Regra: o CEP tem sempre 8 números. Com 7 ou menos ele é recusado, e o sistema não
 * completa com zero, porque não dá para saber qual número faltou.
 */
final class Cep
{
    public const REGRA = 'regex:/^\d{5}-\d{3}$/';

    public const MENSAGEM = 'O CEP deve ter 8 números (ex.: 87501-000).';

    // "87501000" ou "87.501-000" -> "87501-000". Se não tiver 8 números, devolve como veio (e a validação recusa).
    public static function formatar(?string $valor): ?string
    {
        $numeros = preg_replace('/\D/', '', (string) $valor);

        return strlen($numeros) === 8 ? substr($numeros, 0, 5).'-'.substr($numeros, 5) : $valor;
    }
}
