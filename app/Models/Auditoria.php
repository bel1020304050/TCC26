<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    public const UPDATED_AT = null; // só existe created_at

    protected $fillable = ['user_id', 'acao', 'entidade', 'entidade_id', 'detalhes'];

    protected function casts(): array
    {
        return ['detalhes' => 'array'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
