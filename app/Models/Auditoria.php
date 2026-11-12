<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Um registro de auditoria: quem fez, o quê, em qual registro, quando e por quê.
 * Regra: é somente leitura. Depois de gravado, ninguém altera nem apaga (nem o Administrador).
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null; // só existe created_at

    protected $fillable = ['user_id', 'perfil', 'acao', 'entidade', 'entidade_id', 'detalhes', 'motivo'];

    protected function casts(): array
    {
        return ['detalhes' => 'array'];
    }

    // Segurança: qualquer tentativa de alterar ou apagar pelo sistema é barrada
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Registros de auditoria não podem ser alterados.'));
        static::deleting(fn () => throw new LogicException('Registros de auditoria não podem ser apagados.'));
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
