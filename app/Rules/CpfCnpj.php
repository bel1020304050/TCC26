<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra de validação: aceita CPF ou CNPJ com dígitos verificadores corretos,
 * com ou sem pontuação. Uso: 'documento' => ['nullable', new CpfCnpj]
 */
class CpfCnpj implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Documento::valido((string) $value)) {
            $fail('Informe um CPF ou CNPJ válido.');
        }
    }
}
