<?php

namespace App\Support;

use App\Models\Auditoria as RegistroAuditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Atalho para registrar ações na tabela de auditoria (quem fez, o quê, em qual registro, quando e por quê).
 * Uso: Auditoria::registrar('inativou', $categoria, motivo: 'Coleção de inverno encerrada');
 *
 * Quem fez: por padrão, o usuário logado. Ações automáticas (ex.: cancelamento por falta de estoque)
 * usam sistema: true e aparecem como "Sistema" no relatório.
 */
final class Auditoria
{
    public const SISTEMA = 'sistema';

    public static function registrar(string $acao, Model $registro, array $detalhes = [], ?string $motivo = null,
        ?User $autor = null, bool $sistema = false): void
    {
        $autor = $sistema ? null : ($autor ?? Auth::user());

        RegistroAuditoria::create([
            'user_id' => $autor?->id,
            'perfil' => $sistema ? self::SISTEMA : $autor?->perfil,
            'acao' => $acao,
            'entidade' => $registro->getTable(),
            'entidade_id' => $registro->getKey(),
            'detalhes' => $detalhes ?: null,
            'motivo' => $motivo,
        ]);
    }
}
