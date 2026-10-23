<?php

namespace App\Models;

use App\Models\Concerns\TemStatus;
use App\Support\Documento;
use App\Support\Status;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fornecedor extends Model
{
    use HasFactory, TemStatus;

    // O Laravel deduziria "fornecedors"; em português o plural é "fornecedores"
    protected $table = 'fornecedores';

    protected $fillable = ['nome', 'documento', 'telefone', 'email'];

    protected $attributes = ['status' => Status::ATIVO];

    // Salva o CPF/CNPJ só com números (facilita a pesquisa e a regra de único)
    protected function documento(): Attribute
    {
        return Attribute::make(
            set: fn (?string $valor) => ($numeros = Documento::limpar($valor)) === '' ? null : $numeros,
        );
    }

    // Para exibir na tela: 12.345.678/0001-95
    public function documentoFormatado(): ?string
    {
        return Documento::formatar($this->documento);
    }

    public function produtos(): HasMany
    {
        return $this->hasMany(Produto::class);
    }

    // Regra da 3.29: fornecedor com produtos ativos não pode ser inativado
    public function possuiProdutosAtivos(): bool
    {
        return $this->produtos()->ativos()->exists();
    }
}
