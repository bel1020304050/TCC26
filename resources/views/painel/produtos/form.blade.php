@extends('layouts.painel')

@php $editando = $produto->exists; @endphp

@section('titulo', $editando ? 'Editar produto' : 'Novo produto')
@section('trilha', 'Painel › Produtos › '.($editando ? $produto->nome.' ('.$produto->variacao().') › Editar' : 'Novo produto'))

@section('conteudo')
{{-- Story 3.1 – Cadastrar (Figura 2) e 3.3 – Editar Produto (Figura 4) --}}
<div class="mm-card p-4">
    <form method="POST" enctype="multipart/form-data" novalidate
          action="{{ $editando ? route('painel.produtos.update', $produto) : route('painel.produtos.store') }}">
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="row">
            <x-campo class="col-md-6" name="nome" label="Nome do produto" obrigatorio maxlength="120" :valor="$produto->nome" />
            <x-campo class="col-md-3" name="preco" label="Preço (R$)" obrigatorio inputmode="decimal" placeholder="0,00"
                     :valor="$produto->preco !== null ? number_format((float) $produto->preco, 2, ',', '') : null" dica="Maior que zero" />
            <x-campo class="col-md-3" name="estoque" label="Estoque" type="number" obrigatorio min="0" step="1"
                     :valor="$produto->estoque" :dica="$editando ? 'Para repor, altere a quantidade' : 'Não pode ser negativo'" />

            <div class="col-md-6 mb-3">
                <label for="descricao" class="form-label">Descrição <span class="opcional">(opcional)</span></label>
                <textarea id="descricao" name="descricao" rows="4" maxlength="2000"
                          class="form-control @error('descricao') is-invalid @enderror">{{ old('descricao', $produto->descricao) }}</textarea>
                @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="col-md-6">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="categoria_id" class="form-label">Categoria <span class="obrigatorio">*</span></label>
                        <select id="categoria_id" name="categoria_id" class="form-select @error('categoria_id') is-invalid @enderror" required>
                            <option value="">Selecione</option>
                            @foreach ($categorias as $categoria)
                                <option value="{{ $categoria->id }}" @selected((int) old('categoria_id', $produto->categoria_id) === $categoria->id)>
                                    {{ $categoria->nome }}{{ $categoria->isAtivo() ? '' : ' (inativa)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('categoria_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Somente categorias ativas</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fornecedor_id" class="form-label">Fornecedor <span class="opcional">(opcional)</span></label>
                        <select id="fornecedor_id" name="fornecedor_id" class="form-select @error('fornecedor_id') is-invalid @enderror">
                            <option value="">Sem fornecedor</option>
                            @foreach ($fornecedores as $fornecedor)
                                <option value="{{ $fornecedor->id }}" @selected((int) old('fornecedor_id', $produto->fornecedor_id) === $fornecedor->id)>
                                    {{ $fornecedor->nome }}{{ $fornecedor->isAtivo() ? '' : ' (inativo)' }}
                                </option>
                            @endforeach
                        </select>
                        @error('fornecedor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">Somente fornecedores ativos</div>
                        @enderror
                    </div>
                    <x-campo class="col-md-6" name="tamanho" label="Tamanho" obrigatorio maxlength="20" :valor="$produto->tamanho"
                             dica="Use “Único” se não se aplicar" list="sugestoes-tamanho" />
                    <x-campo class="col-md-6" name="cor" label="Cor" obrigatorio maxlength="30" :valor="$produto->cor"
                             dica="Use “Não se aplica” se for o caso" />
                </div>
            </div>

            @if ($editando)
                <div class="col-md-3 mb-3">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="{{ $produto->statusLabel() }}" disabled>
                    <div class="form-text">Alterado só pela ação Inativar/Reativar.</div>
                </div>
            @else
                <div class="col-md-3 mb-3">
                    <label for="status" class="form-label">Status inicial <span class="obrigatorio">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(old('status') === 'inativo')>Inativo</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif

            <div class="col-md-9 mb-3">
                <label for="imagem" class="form-label">Imagem <span class="opcional">(opcional)</span></label>
                <div class="d-flex gap-3 align-items-start">
                    <img id="previa-imagem" src="{{ $produto->imagemUrl() }}" alt="Prévia" class="rounded border" width="72" height="72" style="object-fit: cover;">
                    <div class="flex-grow-1">
                        <input type="file" id="imagem" name="imagem" accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                               class="form-control @error('imagem') is-invalid @enderror">
                        @error('imagem')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="form-text">JPG ou PNG de até 2 MB. Sem imagem, a vitrine usa a imagem padrão.</div>
                        @enderror
                        @if ($editando && $produto->imagem)
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" value="1" id="remover_imagem" name="remover_imagem">
                                <label class="form-check-label small" for="remover_imagem">Remover imagem atual</label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <datalist id="sugestoes-tamanho">
            @foreach (['PP', 'P', 'M', 'G', 'GG', '36', '38', '40', '42', '44', 'Único'] as $t) <option value="{{ $t }}"> @endforeach
        </datalist>

        <p class="text-muted small mb-0">
            Campos com <span style="color: var(--mm-laranja);">*</span> são obrigatórios.
            Não pode existir outro produto com o mesmo Nome + Categoria + Tamanho + Cor.
        </p>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('painel.produtos.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-mm px-4">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Mostra a prévia da imagem escolhida antes de enviar
    document.getElementById('imagem').addEventListener('change', function (e) {
        const arquivo = e.target.files[0];
        if (arquivo) document.getElementById('previa-imagem').src = URL.createObjectURL(arquivo);
    });
</script>
@endpush
