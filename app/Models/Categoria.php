<?php

namespace App\Models;

use App\Models\Concerns\TemStatus;
use App\Support\Status;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categoria extends Model
{
    use HasFactory, TemStatus;

    // "status" fica de fora: só muda pelas ações Inativar/Reativar (story 3.16)
    protected $fillable = ['nome', 'descricao'];

    protected $attributes = ['status' => Status::ATIVO];

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    // Regra da 3.17: categoria com produtos ativos não pode ser inativada
    public function possuiProdutosAtivos(): bool
    {
        return $this->produtos()->ativos()->exists();
    }

    // Nome único sem diferenciar maiúsculas e minúsculas ("vestidos" = "Vestidos")
    public static function nomeEmUso(string $nome, ?int $ignorarId = null): bool
    {
        return static::query()
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower(trim($nome))])
            ->when($ignorarId, fn (Builder $q) => $q->whereKeyNot($ignorarId))
            ->exists();
    }
}
