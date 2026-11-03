<?php

namespace App\Http\Requests;

use App\Models\Promocao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validação de Promoção — regras das stories 3.22 (cadastrar) e 3.24 (editar).
 */
class PromocaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $promocao = $this->route('promocao');

        return $promocao
            ? Gate::allows('update', $promocao)
            : Gate::allows('create', Promocao::class);
    }

    protected function prepareForValidation(): void
    {
        $promocao = $this->route('promocao');

        // 3.24: promoção já usada em pedido não muda modo nem código (mantém os atuais)
        if ($promocao?->foiUsada()) {
            $this->merge(['modo' => $promocao->modo, 'codigo' => $promocao->codigo]);
        }

        $this->merge([
            'codigo' => $this->input('modo') === Promocao::CUPOM ? (mb_strtoupper(trim((string) $this->input('codigo'))) ?: null) : null,
            // Aceita "20", "20,5" ou "10,00"
            'valor' => $this->numero((string) $this->input('valor')),
            'produto_id' => $this->input('aplicacao') === Promocao::PRODUTO ? $this->input('produto_id') : null,
            'categoria_id' => $this->input('aplicacao') === Promocao::CATEGORIA ? $this->input('categoria_id') : null,
        ]);
    }

    // "1.234,50" -> 1234.50 · "20,5" -> 20.5 · "10.5" -> 10.5 · "" -> null
    private function numero(string $valor): ?string
    {
        $valor = trim(str_replace(['R$', '%', ' '], '', $valor));
        if ($valor === '') {
            return null;
        }

        return str_contains($valor, ',') ? str_replace(',', '.', str_replace('.', '', $valor)) : $valor;
    }

    public function rules(): array
    {
        $promocao = $this->route('promocao');

        return [
            'nome' => ['required', 'string', 'max:80'],
            'modo' => ['required', Rule::in([Promocao::AUTOMATICA, Promocao::CUPOM])],
            'codigo' => [
                Rule::requiredIf($this->input('modo') === Promocao::CUPOM), 'nullable', 'regex:/^[A-Z0-9_-]{3,30}$/',
                Rule::unique('promocoes', 'codigo')->ignore($promocao?->id),
            ],
            'tipo' => ['required', Rule::in([Promocao::PERCENTUAL, Promocao::VALOR])],
            'valor' => ['required', 'numeric', $this->input('tipo') === Promocao::PERCENTUAL ? 'between:1,50' : 'gt:0', 'max:99999'],
            'data_inicio' => ['required', 'date'],
            'data_fim' => ['required', 'date', 'after_or_equal:data_inicio'],
            'aplicacao' => ['required', Rule::in([Promocao::GERAL, Promocao::PRODUTO, Promocao::CATEGORIA])],
            'produto_id' => [Rule::requiredIf($this->input('aplicacao') === Promocao::PRODUTO), 'nullable', 'exists:produtos,id'],
            'categoria_id' => [Rule::requiredIf($this->input('aplicacao') === Promocao::CATEGORIA), 'nullable', 'exists:categorias,id'],
            'status' => $promocao ? ['prohibited'] : ['required', Rule::in(['ativo', 'inativo'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'modo' => 'modo', 'codigo' => 'código do cupom', 'tipo' => 'tipo de desconto', 'valor' => 'valor do desconto',
            'data_inicio' => 'data inicial', 'data_fim' => 'data final', 'aplicacao' => 'aplicação',
            'produto_id' => 'produto', 'categoria_id' => 'categoria', 'status' => 'status',
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'No modo Cupom, o código é obrigatório.',
            'codigo.unique' => 'Este código de cupom já está em uso.',
            'codigo.regex' => 'Use de 3 a 30 letras, números, hífen ou sublinhado, sem espaços.',
            'valor.between' => 'O desconto percentual deve ficar entre 1% e 50%.',
            'valor.gt' => 'O desconto em valor fixo deve ser maior que zero.',
            'data_fim.after_or_equal' => 'A data final não pode ser anterior à data inicial.',
            'produto_id.required' => 'Escolha o produto que recebe a promoção.',
            'categoria_id.required' => 'Escolha a categoria que recebe a promoção.',
        ];
    }

    // Produto/categoria escolhidos precisam estar ativos (o atual é aceito na edição)
    public function after(): array
    {
        return [function (Validator $validator) {
            $promocao = $this->route('promocao');
            foreach (['produto_id' => \App\Models\Produto::class, 'categoria_id' => \App\Models\Categoria::class] as $campo => $classe) {
                $id = $this->input($campo);
                if ($id && $promocao?->{$campo} != $id && ! $classe::ativos()->whereKey($id)->exists()) {
                    $validator->errors()->add($campo, 'Escolha um item ativo.');
                }
            }
        }];
    }
}
