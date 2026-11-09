<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validação de "Editar meus dados". Usa as mesmas regras do cadastro, sem a senha
 * (que tem formulário próprio) e com o e-mail único ignorando a própria conta.
 */
class EditarContaRequest extends CriarContaRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isCliente() ?? false;
    }

    public function rules(): array
    {
        $regras = parent::rules();
        unset($regras['password']);

        // Segurança: usa sempre a conta logada, nunca um id vindo do navegador
        $regras['email'] = ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->user()->id)];

        return $regras;
    }
}
