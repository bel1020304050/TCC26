<?php

namespace App\Support;

/**
 * Funções para CPF e CNPJ: limpar, validar e formatar.
 * Os dois usam "dígitos verificadores": os últimos 2 números são calculados a partir dos
 * anteriores. Se o cálculo não bater, o documento foi digitado errado.
 */
final class Documento
{
    // "12.345.678/0001-95" -> "12345678000195"
    public static function limpar(?string $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor);
    }

    public static function valido(?string $valor): bool
    {
        $numeros = self::limpar($valor);

        return match (strlen($numeros)) {
            11 => self::cpfValido($numeros),
            14 => self::cnpjValido($numeros),
            default => false,
        };
    }

    public static function cpfValido(string $cpf): bool
    {
        // Sequências repetidas (111.111.111-11) passam na conta, mas não são válidas
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $soma = 0;
            for ($i = 0; $i < $t; $i++) {
                $soma += (int) $cpf[$i] * (($t + 1) - $i);
            }
            $digito = ((10 * $soma) % 11) % 10;
            if ((int) $cpf[$t] !== $digito) {
                return false;
            }
        }

        return true;
    }

    public static function cnpjValido(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];

        foreach ([12, 13] as $indice => $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cnpj[$i] * $pesos[$indice][$i];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    // "12345678000195" -> "12.345.678/0001-95"; "52998224725" -> "529.982.247-25"
    public static function formatar(?string $valor): ?string
    {
        $n = self::limpar($valor);

        return match (strlen($n)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $n),
            14 => preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $n),
            default => $valor ?: null,
        };
    }
}
