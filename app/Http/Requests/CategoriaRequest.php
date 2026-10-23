<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de Cadastrar (3.14) e Editar (3.16) Categoria.
 */
class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $categoria = $this->route('categoria');

        return $categoria
            ? $this->user()->can('update', $categoria)
            : $this->user()->can('create', Categoria::class);
    }

    public function rules(): array
    {
        $categoria = $this->route('categoria');

        $regras = [
            'nome' => [
                'required', 'string', 'max:60',
                // Único inclusive entre inativas e sem diferenciar maiúsculas/minúsculas
                function (string $attribute, mixed $value, Closure $fail) use ($categoria) {
                    if (Categoria::nomeEmUso((string) $value, $categoria?->id)) {
                        $fail('Já existe uma categoria com este nome.');
                    }
                },
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
        ];

        // Status inicial só no cadastro; na edição ele não muda (só por Inativar/Reativar)
        if (! $categoria) {
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function attributes(): array
    {
        return ['nome' => 'nome da categoria', 'descricao' => 'descrição', 'status' => 'status inicial'];
    }
}
