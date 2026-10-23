@extends('layouts.painel')

@php $editando = $fornecedor->exists; @endphp

@section('titulo', $editando ? 'Editar fornecedor' : 'Novo fornecedor')
@section('trilha', 'Painel › Fornecedores › '.($editando ? $fornecedor->nome.' › Editar' : 'Novo fornecedor'))

@section('conteudo')
{{-- Story 3.26 – Cadastrar (Figura 27) e 3.28 – Editar Fornecedor (Figura 29) --}}
<div class="mm-card p-4" style="max-width: 780px;">
    <form method="POST" action="{{ $editando ? route('painel.fornecedores.update', $fornecedor) : route('painel.fornecedores.store') }}" novalidate>
        @csrf
        @if ($editando) @method('PUT') @endif

        <div class="row">
            <x-campo class="col-12" name="nome" label="Nome / Razão social" obrigatorio maxlength="150" :valor="$fornecedor->nome" />
            <x-campo class="col-md-6" name="documento" label="CPF / CNPJ" opcional :valor="$fornecedor->documentoFormatado()"
                     dica="Validado e único quando informado" inputmode="numeric" maxlength="18" data-mascara-documento />
            <x-campo class="col-md-6" name="telefone" label="Telefone" opcional maxlength="20" :valor="$fornecedor->telefone" placeholder="(44) 3622-1000" />
            <x-campo class="col-md-6" name="email" label="E-mail" type="email" opcional maxlength="150" :valor="$fornecedor->email" />

            @if ($editando)
                <div class="col-md-6 mb-3">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" value="{{ $fornecedor->statusLabel() }}" disabled>
                    <div class="form-text">Alterado só pela ação Inativar/Reativar.</div>
                </div>
            @else
                <div class="col-md-6 mb-3">
                    <label for="status" class="form-label">Status inicial <span class="obrigatorio">*</span></label>
                    <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                        <option value="ativo" @selected(old('status', 'ativo') === 'ativo')>Ativo</option>
                        <option value="inativo" @selected(old('status') === 'inativo')>Inativo</option>
                    </select>
                    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif
        </div>

        @if ($editando)
            <p class="text-muted small">{{ $fornecedor->produtos_count }} produto(s) vinculado(s) continuam associados a este fornecedor.</p>
        @endif

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a href="{{ route('painel.fornecedores.index') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-mm px-4">{{ $editando ? 'Salvar alterações' : 'Cadastrar' }}</button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Máscara de CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00) conforme a quantidade de dígitos
    document.querySelectorAll('[data-mascara-documento]').forEach(function (campo) {
        campo.addEventListener('input', function () {
            const d = campo.value.replace(/\D/g, '').slice(0, 14);
            campo.value = d.length <= 11
                ? d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2')
                : d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2');
        });
    });
</script>
@endpush
