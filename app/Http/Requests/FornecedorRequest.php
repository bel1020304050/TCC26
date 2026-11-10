<?php

namespace App\Http\Requests;

use App\Models\Fornecedor;
use App\Models\User;
use App\Rules\CpfCnpj;
use App\Support\Cep;
use App\Support\Documento;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do formulário de Fornecedor (cadastrar e editar).
 * Regra: o tipo de pessoa decide o documento aceito (Física = CPF, Jurídica = CNPJ, inclusive alfanumérico).
 * O documento continua opcional; o endereço completo é obrigatório.
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

    // Padroniza antes de validar: CEP "87501000" vira "87501-000", UF em maiúsculas e e-mail em minúsculas
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : null,
            'cep' => Cep::formatar($this->input('cep')),
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))),
        ]);
    }

    public function rules(): array
    {
        $fornecedor = $this->route('fornecedor');
        $tipoDocumento = $this->input('tipo_pessoa') === Fornecedor::FISICA ? Documento::CPF : Documento::CNPJ;

        $regras = [
            'nome' => ['required', 'string', 'max:150'],
            'tipo_pessoa' => ['required', Rule::in(array_keys(Fornecedor::TIPOS))],
            'documento' => [
                'nullable', new CpfCnpj($tipoDocumento),
                // Único entre todos os fornecedores, inclusive inativos (comparando sem pontuação)
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
            'cep' => ['required', Cep::REGRA],
            'rua' => ['required', 'string', 'max:120'],
            'numero' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:60'],
            'bairro' => ['required', 'string', 'max:80'],
            'cidade' => ['required', 'string', 'max:80'],
            'uf' => ['required', Rule::in(User::UFS)],
        ];

        if (! $fornecedor) {
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'tipo_pessoa.required' => 'Escolha se o fornecedor é Pessoa Física ou Pessoa Jurídica.',
            'cep.regex' => Cep::MENSAGEM,
            'uf.in' => 'Selecione uma UF válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome/razão social', 'tipo_pessoa' => 'tipo de pessoa', 'documento' => 'CPF/CNPJ',
            'status' => 'status inicial', 'rua' => 'rua/avenida', 'numero' => 'número',
        ];
    }
}
