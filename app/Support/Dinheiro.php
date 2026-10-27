<?php

namespace App\Support;

/**
 * Formatação de valores em reais.
 * Dinheiro::formatar(129.9) -> "R$ 129,90"
 */
final class Dinheiro
{
    public static function formatar(float|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    // Arredonda em 2 casas, para os cálculos de total não acumularem "centavos quebrados"
    public static function arredondar(float $valor): float
    {
        return round($valor, 2);
    }
}
