<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Support\Status;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação de Cadastrar (3.1) e Editar (3.3) Produto.
 */
class ProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $produto = $this->route('produto');

        return $produto
            ? $this->user()->can('update', $produto)
            : $this->user()->can('create', Produto::class);
    }

    // Aceita preço no formato brasileiro: "1.299,90" vira "1299.90"
    protected function prepareForValidation(): void
    {
        $preco = trim((string) $this->input('preco'));
        if (str_contains($preco, ',')) {
            $preco = str_replace(['.', ','], ['', '.'], $preco);
        }

        $this->merge([
            'preco' => $preco,
            'nome' => trim((string) $this->input('nome')),
            'tamanho' => trim((string) $this->input('tamanho')),
            'cor' => trim((string) $this->input('cor')),
        ]);
    }

    public function rules(): array
    {
        /** @var Produto|null $produto */
        $produto = $this->route('produto');

        $regras = [
            'nome' => ['required', 'string', 'max:120', $this->regraVariacaoUnica($produto)],
            'descricao' => ['nullable', 'string', 'max:2000'],
            'preco' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'estoque' => ['required', 'integer', 'min:0', 'max:1000000'],
            'tamanho' => ['required', 'string', 'max:20'],
            'cor' => ['required', 'string', 'max:30'],
            // Só categorias e fornecedores Ativos. Na edição, o vínculo atual é aceito mesmo se ficou inativo.
            'categoria_id' => ['required', $this->existeAtivo('categorias', $produto?->categoria_id)],
            'fornecedor_id' => ['nullable', $this->existeAtivo('fornecedores', $produto?->fornecedor_id)],
            // RNF.02 da 3.1: JPG ou PNG de até 2 MB
            'imagem' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remover_imagem' => ['nullable', 'boolean'],
        ];

        if (! $produto) {
            $regras['status'] = ['required', Rule::in(Status::OPCOES)];
        }

        return $regras;
    }

    public function messages(): array
    {
        return [
            'preco.gt' => 'O preço deve ser maior que zero.',
            'estoque.min' => 'O estoque não pode ser negativo.',
            'imagem.max' => 'A imagem deve ter no máximo 2 MB.',
            'imagem.mimes' => 'A imagem deve ser JPG ou PNG.',
            'imagem.image' => 'A imagem deve ser JPG ou PNG.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome do produto', 'descricao' => 'descrição', 'preco' => 'preço',
            'categoria_id' => 'categoria', 'fornecedor_id' => 'fornecedor', 'status' => 'status inicial',
        ];
    }

    private function existeAtivo(string $tabela, ?int $atual): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists($tabela, 'id')->where(function ($q) use ($atual) {
            $q->where('status', Status::ATIVO);
            if ($atual) {
                $q->orWhere('id', $atual);
            }
        });
    }

    // Regra da 3.1: não pode existir outro produto com Nome + Categoria + Tamanho + Cor iguais
    private function regraVariacaoUnica(?Produto $produto): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($produto) {
            $existe = Produto::query()
                ->whereRaw('LOWER(nome) = ?', [mb_strtolower((string) $value)])
                ->where('categoria_id', $this->input('categoria_id'))
                ->whereRaw('LOWER(tamanho) = ?', [mb_strtolower((string) $this->input('tamanho'))])
                ->whereRaw('LOWER(cor) = ?', [mb_strtolower((string) $this->input('cor'))])
                ->when($produto, fn ($q) => $q->whereKeyNot($produto->id))
                ->exists();

            if ($existe) {
                $fail('Já existe um produto com este nome, categoria, tamanho e cor.');
            }
        };
    }
}
