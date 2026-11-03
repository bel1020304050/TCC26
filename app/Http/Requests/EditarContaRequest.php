<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Story 3.11 – Editar Meus Dados. Mesmas regras do cadastro (3.9), sem a senha
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

        // Sempre a conta da sessão — nunca um id vindo do navegador (RNF.01 da 3.11)
        $regras['email'] = ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($this->user()->id)];

        return $regras;
    }
}
