<?php

namespace App\Support;

/**
 * Funções para CPF e CNPJ: limpar, validar e formatar.
 * Os dois usam "dígitos verificadores": os últimos 2 caracteres são calculados a partir dos
 * anteriores. Se o cálculo não bater, o documento foi digitado errado.
 *
 * Regra: desde julho de 2026 a Receita Federal emite CNPJ alfanumérico (ex.: 12.ABC.345/01DE-35).
 * As 12 primeiras posições aceitam letras e números; os 2 dígitos verificadores continuam numéricos.
 * Os CNPJs antigos, só com números, continuam válidos pelo mesmo cálculo.
 */
final class Documento
{
    public const CPF = 'cpf';

    public const CNPJ = 'cnpj';

    // "12.abc.345/01de-35" -> "12ABC34501DE35"; "529.982.247-25" -> "52998224725"
    public static function limpar(?string $valor): string
    {
        return preg_replace('/[^0-9A-Z]/', '', mb_strtoupper((string) $valor));
    }

    // Valida pelo tamanho: 11 caracteres = CPF, 14 = CNPJ
    public static function valido(?string $valor): bool
    {
        $limpo = self::limpar($valor);

        return match (strlen($limpo)) {
            11 => self::cpfValido($limpo),
            14 => self::cnpjValido($limpo),
            default => false,
        };
    }

    public static function cpfValido(?string $valor): bool
    {
        $cpf = self::limpar($valor);

        // Só números, e sequências repetidas (111.111.111-11) passam na conta, mas não são válidas
        if (! preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
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

    public static function cnpjValido(?string $valor): bool
    {
        $cnpj = self::limpar($valor);

        if (! preg_match('/^[0-9A-Z]{12}\d{2}$/', $cnpj) || preg_match('/^(.)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [[5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2], [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]];

        foreach ([12, 13] as $indice => $posicao) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                // Cada caractere vale o código ASCII menos 48: '0'..'9' = 0..9, 'A' = 17, 'B' = 18 ...
                $soma += (ord($cnpj[$i]) - 48) * $pesos[$indice][$i];
            }
            $resto = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;
            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    // "12345678000195" -> "12.345.678/0001-95"; "12ABC34501DE35" -> "12.ABC.345/01DE-35"; "52998224725" -> "529.982.247-25"
    public static function formatar(?string $valor): ?string
    {
        $limpo = self::limpar($valor);

        return match (strlen($limpo)) {
            11 => preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $limpo),
            14 => preg_replace('/(\w{2})(\w{3})(\w{3})(\w{4})(\w{2})/', '$1.$2.$3/$4-$5', $limpo),
            default => $valor ?: null,
        };
    }
}
