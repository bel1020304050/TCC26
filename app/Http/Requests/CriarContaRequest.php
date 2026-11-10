<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\CpfCnpj;
use App\Support\Cep;
use App\Support\Documento;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validação do cadastro de cliente ("Criar conta").
 */
class CriarContaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // página pública
    }

    // Padroniza os dados antes de validar (ex.: CEP "87501000" vira "87501-000")
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))),
            'cep' => Cep::formatar($this->input('cep')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // Segurança: o domínio da loja é só da equipe, para ninguém criar conta se passando pela loja
            'email' => ['required', 'email', 'max:150', 'doesnt_end_with:'.UsuarioRequest::DOMINIO, Rule::unique('users', 'email')],
            'cpf' => $this->regrasCpf(),
            'telefone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'cep' => ['required', Cep::REGRA],
            'rua' => ['required', 'string', 'max:120'],
            'numero' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:60'],
            'bairro' => ['required', 'string', 'max:80'],
            'cidade' => ['required', 'string', 'max:80'],
            'uf' => ['required', Rule::in(User::UFS)],
        ];
    }

    /**
     * CPF obrigatório, válido e único (comparando só os números).
     * Regra: o pagamento online exige o CPF do comprador.
     */
    protected function regrasCpf(?int $ignorarId = null): array
    {
        return [
            'required', new CpfCnpj(Documento::CPF),
            function (string $attribute, mixed $value, Closure $fail) use ($ignorarId) {
                $existe = User::where('cpf', Documento::limpar($value))
                    ->when($ignorarId, fn ($q) => $q->whereKeyNot($ignorarId))
                    ->exists();
                if ($existe) {
                    $fail('Este CPF já está cadastrado em outra conta.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está em uso.',
            'email.doesnt_end_with' => 'Esse domínio é reservado à equipe da loja. Use o seu e-mail pessoal.',
            'cep.regex' => Cep::MENSAGEM,
            'uf.in' => 'Selecione uma UF válida.',
        ];
    }

    public function attributes(): array
    {
        return ['cpf' => 'CPF'];
    }
}
