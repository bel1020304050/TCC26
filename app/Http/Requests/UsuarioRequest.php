<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do formulário de usuário da equipe (cadastrar e editar).
 * A senha não é digitada aqui: o sistema gera uma senha provisória (ver UsuarioController).
 */
class UsuarioRequest extends FormRequest
{
    // Regra: a equipe usa o e-mail da loja
    public const DOMINIO = '@marymodas.com.br';

    public function authorize(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario
            ? $this->user()->can('update', $usuario)
            : $this->user()->can('create', User::class);
    }

    // E-mail em minúsculas antes de validar: "Ana@MaryModas.com.br" vira "ana@marymodas.com.br"
    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        /** @var User|null $usuario */
        $usuario = $this->route('usuario');

        $regras = [
            'name' => ['required', 'string', 'max:120'],
            // Único em todo o sistema, incluindo contas de clientes
            'email' => ['required', 'email', 'max:150', 'ends_with:'.self::DOMINIO, Rule::unique('users', 'email')->ignore($usuario?->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'perfil' => [
                'required',
                Rule::in(User::PERFIS_EQUIPE), // cliente nunca é criado por aqui
                $this->regraProprioPerfil($usuario),
            ],
        ];

        if (! $usuario) {
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function messages(): array
    {
        return ['email.ends_with' => 'Use o e-mail da loja (terminado em '.self::DOMINIO.').'];
    }

    public function attributes(): array
    {
        return ['status' => 'status inicial'];
    }

    // Regra: o Administrador não pode alterar o próprio perfil (evita perder o acesso por engano)
    private function regraProprioPerfil(?User $usuario): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($usuario) {
            if ($usuario && $usuario->is($this->user()) && $value !== $usuario->perfil) {
                $fail('Você não pode alterar o seu próprio perfil.');
            }
        };
    }
}
