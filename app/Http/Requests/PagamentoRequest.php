<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação do formulário de pagamento. Os dados do cartão são validados aqui,
 * passam pelo provedor e são descartados — nunca vão para o banco.
 */
class PagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // o dono do pedido é conferido no controller (Policy)
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('cartao_numero')) {
            $this->merge(['cartao_numero' => preg_replace('/\D/', '', (string) $this->input('cartao_numero'))]);
        }
    }

    public function rules(): array
    {
        $cartao = Rule::requiredIf($this->input('forma') === 'cartao');

        return [
            'forma' => ['required', Rule::in(['pix', 'cartao', 'boleto'])],
            'cartao_numero' => [$cartao, 'nullable', 'regex:/^\d{13,19}$/'],
            'cartao_nome' => [$cartao, 'nullable', 'string', 'max:60'],
            'cartao_validade' => [$cartao, 'nullable', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/', $this->validadeFutura(...)],
            'cartao_cvv' => [$cartao, 'nullable', 'regex:/^\d{3,4}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'forma' => 'forma de pagamento',
            'cartao_numero' => 'número do cartão',
            'cartao_nome' => 'nome impresso no cartão',
            'cartao_validade' => 'validade',
            'cartao_cvv' => 'CVV',
        ];
    }

    public function messages(): array
    {
        return [
            'cartao_numero.regex' => 'Informe um número de cartão com 13 a 19 dígitos.',
            'cartao_validade.regex' => 'Use o formato MM/AA.',
            'cartao_cvv.regex' => 'O CVV tem 3 ou 4 dígitos.',
        ];
    }

    // Cartão vencido é recusado já na validação
    private function validadeFutura(string $atributo, mixed $valor, Closure $falhar): void
    {
        if (! preg_match('/^(\d{2})\/(\d{2})$/', (string) $valor, $m)) {
            return;
        }
        $fimDoMes = now()->setDate(2000 + (int) $m[2], (int) $m[1], 1)->endOfMonth();
        if ($fimDoMes->isPast()) {
            $falhar('O cartão está vencido.');
        }
    }
}
