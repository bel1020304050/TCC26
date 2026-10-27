<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Validação de Cadastrar (3.5) e Editar (3.7) Usuário interno.
 */
class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario
            ? $this->user()->can('update', $usuario)
            : $this->user()->can('create', User::class);
    }

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
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'telefone' => ['nullable', 'string', 'max:20'],
            'cargo' => ['nullable', 'string', 'max:60'], // só informativo
            'perfil' => [
                'required',
                Rule::in([User::PERFIL_FUNCIONARIO, User::PERFIL_ADMIN]),
                $this->regraProprioPerfil($usuario),
            ],
        ];

        if ($usuario) {
            // Na edição a senha é opcional: em branco mantém a atual
            $regras['password'] = ['nullable', 'confirmed', Password::min(8)];
        } else {
            $regras['password'] = ['required', 'confirmed', Password::min(8)];
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function attributes(): array
    {
        return ['password' => 'senha', 'status' => 'status inicial'];
    }

    // Regra da 3.7: o Administrador não pode alterar o próprio perfil
    private function regraProprioPerfil(?User $usuario): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($usuario) {
            if ($usuario && $usuario->is($this->user()) && $value !== $usuario->perfil) {
                $fail('Você não pode alterar o seu próprio perfil.');
            }
        };
    }
}
