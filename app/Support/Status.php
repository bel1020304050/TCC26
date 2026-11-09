<?php

namespace App\Support;

/**
 * Status padronizados dos cadastros com exclusão lógica (Ativo/Inativo).
 */
final class Status
{
    public const ATIVO = 'ativo';
    public const INATIVO = 'inativo';

    public const OPCOES = [self::ATIVO, self::INATIVO];

    public static function label(string $status): string
    {
        return $status === self::ATIVO ? 'Ativo' : 'Inativo';
    }
}
