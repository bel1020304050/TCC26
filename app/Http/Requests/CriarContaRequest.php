<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validação da story 3.9 – Criar Conta.
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
        $cep = preg_replace('/\D/', '', (string) $this->input('cep'));

        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'uf' => mb_strtoupper(trim((string) $this->input('uf'))),
            'cep' => strlen($cep) === 8 ? substr($cep, 0, 5).'-'.substr($cep, 5) : $this->input('cep'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'telefone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'cep' => ['required', 'regex:/^\d{5}-\d{3}$/'],
            'rua' => ['required', 'string', 'max:120'],
            'numero' => ['required', 'string', 'max:10'],
            'complemento' => ['nullable', 'string', 'max:60'],
            'bairro' => ['required', 'string', 'max:80'],
            'cidade' => ['required', 'string', 'max:80'],
            'uf' => ['required', Rule::in(User::UFS)],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Este e-mail já está em uso.',
            'cep.regex' => 'Informe o CEP no formato 00000-000.',
            'uf.in' => 'Selecione uma UF válida.',
        ];
    }
}
