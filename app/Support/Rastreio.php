<?php

namespace App\Support;

use App\Models\Pedido;

/**
 * Código de rastreio SIMULADO, no formato dos Correios (padrão internacional S10 da UPU):
 * 2 letras + 8 números + 1 dígito verificador + "BR". Ex.: MM12345678 5 BR -> "MM123456785BR".
 * O dígito verificador é calculado como nos Correios, então o código "parece" real,
 * mas não existe no sistema dos Correios: o rastreio de verdade fica para uma versão futura.
 */
final class Rastreio
{
    private const PESOS = [8, 6, 4, 2, 3, 5, 9, 7];

    public static function gerar(): string
    {
        do {
            $numero = str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
            $codigo = 'MM'.$numero.self::digito($numero).'BR';
        } while (Pedido::where('codigo_rastreio', $codigo)->exists()); // nunca repete

        return $codigo;
    }

    // Dígito verificador do padrão S10: soma ponderada, resto da divisão por 11
    public static function digito(string $oitoNumeros): int
    {
        $soma = 0;
        foreach (str_split($oitoNumeros) as $i => $numero) {
            $soma += (int) $numero * self::PESOS[$i];
        }
        $digito = 11 - ($soma % 11);

        return match ($digito) {
            10 => 0,
            11 => 5,
            default => $digito,
        };
    }

    public static function valido(string $codigo): bool
    {
        return (bool) preg_match('/^[A-Z]{2}(\d{8})(\d)BR$/', $codigo, $m) && self::digito($m[1]) === (int) $m[2];
    }
}
