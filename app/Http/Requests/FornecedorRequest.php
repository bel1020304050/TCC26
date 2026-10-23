<?php

namespace App\Http\Requests;

use App\Models\Fornecedor;
use App\Rules\CpfCnpj;
use App\Support\Documento;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de Cadastrar (3.26) e Editar (3.28) Fornecedor.
 * As regras de obrigatoriedade são as mesmas nos dois: CPF/CNPJ continua opcional.
 */
class FornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fornecedor = $this->route('fornecedor');

        return $fornecedor
            ? $this->user()->can('update', $fornecedor)
            : $this->user()->can('create', Fornecedor::class);
    }

    public function rules(): array
    {
        $fornecedor = $this->route('fornecedor');

        $regras = [
            'nome' => ['required', 'string', 'max:150'],
            'documento' => [
                'nullable', new CpfCnpj,
                // Único entre todos os fornecedores, inclusive inativos (comparando só os números)
                function (string $attribute, mixed $value, Closure $fail) use ($fornecedor) {
                    $existe = Fornecedor::where('documento', Documento::limpar($value))
                        ->when($fornecedor, fn ($q) => $q->whereKeyNot($fornecedor->id))
                        ->exists();
                    if ($existe) {
                        $fail('Este documento já pertence a outro fornecedor.');
                    }
                },
            ],
            'telefone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
        ];

        if (! $fornecedor) {
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function attributes(): array
    {
        return ['nome' => 'nome/razão social', 'documento' => 'CPF/CNPJ', 'status' => 'status inicial'];
    }
}
