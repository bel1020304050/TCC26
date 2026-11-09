<?php

namespace App\Rules;

use App\Support\Documento;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regra de validação de CPF/CNPJ, com ou sem pontuação.
 * Uso: new CpfCnpj('cpf') só aceita CPF; new CpfCnpj('cnpj') só aceita CNPJ (inclusive alfanumérico);
 * new CpfCnpj sem tipo aceita qualquer um dos dois.
 */
class CpfCnpj implements ValidationRule
{
    public function __construct(private ?string $tipo = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valido = match ($this->tipo) {
            Documento::CPF => Documento::cpfValido((string) $value),
            Documento::CNPJ => Documento::cnpjValido((string) $value),
            default => Documento::valido((string) $value),
        };

        if (! $valido) {
            $fail(match ($this->tipo) {
                Documento::CPF => 'Informe um CPF válido.',
                Documento::CNPJ => 'Informe um CNPJ válido.',
                default => 'Informe um CPF ou CNPJ válido.',
            });
        }
    }
}
