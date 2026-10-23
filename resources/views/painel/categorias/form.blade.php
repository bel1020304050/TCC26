@extends('layouts.painel')

@php $editando = $categoria->exists; @endphp

@section('titulo', $editando ? 'Editar categoria' : 'Nova categoria')
@section('trilha', 'Painel › Categorias › '.($editando ? $categoria->nome.' › Editar' : 'Nova categoria'))

@section('conteudo')
{{-- Story 3.14 – Cadastrar (Figura 15) e 3.16 – Editar Categoria (Figura 17) --}}
<div class="mm-card p-4" style="max-width: 680px;">
    <form method="POST" action="{{ $editando ? route('painel.categorias.update', $categoria) : route('painel.categorias.store') }}" novalidate>
        @csrf
        @if ($editando) @method('PUT') @endif

        <x-campo name="nome" label="Nome da categoria" obrigatorio maxlength="60" :valor="$categoria->nome"
                 dica="Deve ser único, inclusive entre categorias inativas" />

        <div class="mb-3">
            <label for="descricao" class="form-label">Descrição <span class="opcional">(opcional)</span></label>
            <textarea id="descricao" name="descricao" rows="3" maxlength="255"
                      class="form-control @error('descricao') is-invalid @enderror">{{ old('descricao', $categoria->descricao) }}</textarea>
            @error('descricao') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        @if ($editando)
            <div class="mb-3">
                <label class="form-label">Status</label>
                <input type="text" class="form-control" value="{{ $categoria->statusLabel() }}" disabled>
                <div class="form-text">Alterado só pela ação Inativar/Reativar.</div>
            </div>
            <p class="text-muted small">{{ $categoria->produtos_count }} produto(s) vinculado(s) continuam associados a esta categoria após a edição.</p>
        @else
            <div class="mb-3">
                <label for="status" class="form-label">Status inicial <span class="obrigatorio">*</span></label>
                <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                    <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativo</option>
                    <option value="inativo" @selected(old('status') === 'inativo')>Inativo</option>
                </select>
                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
        @endif

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="{{ route('painel.categorias.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-mm px-4">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
        </div>
    </form>
</div>
@endsection
