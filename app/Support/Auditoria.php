<?php

namespace App\Support;

use App\Models\Auditoria as RegistroAuditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Atalho para registrar ações na auditoria (seção 2.3).
 * Uso: Auditoria::registrar('inativou', $categoria);
 */
final class Auditoria
{
    public static function registrar(string $acao, Model $registro, array $detalhes = []): void
    {
        RegistroAuditoria::create([
            'user_id' => Auth::id(),
            'acao' => $acao,
            'entidade' => $registro->getTable(),
            'entidade_id' => $registro->getKey(),
            'detalhes' => $detalhes ?: null,
        ]);
    }
}
