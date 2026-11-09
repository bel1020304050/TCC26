@extends('layouts.painel')

@php
    $editando = $fornecedor->exists;
    $tipo = old('tipo_pessoa', $editando ? $fornecedor->tipo_pessoa : null);
@endphp

@section('titulo', $editando ? 'Editar fornecedor' : 'Novo fornecedor')
@section('trilha', 'Painel › Fornecedores › '.($editando ? $fornecedor->nome.' › Editar' : 'Novo fornecedor'))

@section('conteudo')
{{-- Formulário de fornecedor (o mesmo para cadastrar e editar) --}}
<div class="mm-card p-4" style="max-width: 900px;">
    <form method="POST" action="{{ $editando ? route('painel.fornecedores.update', $fornecedor) : route('painel.fornecedores.store') }}" novalidate>
        @csrf
        @if ($editando) @method('PUT') @endif

        <h2 class="h6 fw-semibold mb-3">Dados do fornecedor</h2>
        <div class="row">
            <x-campo class="col-12" name="nome" label="Nome / Razão social" obrigatorio maxlength="150" :valor="$fornecedor->nome" />

            {{-- Regra: o tipo de pessoa decide se o documento é CPF ou CNPJ --}}
            <div class="col-md-6 mb-3">
                <span class="form-label d-block">Tipo de pessoa <span class="obrigatorio">*</span></span>
                <div class="btn-group w-100 @error('tipo_pessoa') is-invalid @enderror" role="group" aria-label="Tipo de pessoa">
                    @foreach (\App\Models\Fornecedor::TIPOS as $valor => $rotulo)
                        <input type="radio" class="btn-check" name="tipo_pessoa" id="tipo_{{ $valor }}" value="{{ $valor }}"
                               autocomplete="off" @checked($tipo === $valor)>
                        <label class="btn btn-outline-secondary" for="tipo_{{ $valor }}">{{ $rotulo }}</label>
                    @endforeach
                </div>
                @error('tipo_pessoa')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @else
                    <div class="form-text">Escolha o tipo para liberar o campo do documento.</div>
                @enderror
            </div>

            <div class="col-md-6 mb-3">
                <label for="documento" class="form-label">
                    <span data-rotulo>{{ $tipo === 'fisica' ? 'CPF' : ($tipo === 'juridica' ? 'CNPJ' : 'CPF / CNPJ') }}</span>
                    <span class="opcional">(opcional)</span>
                </label>
                <input type="text" id="documento" name="documento" value="{{ old('documento', $fornecedor->documentoFormatado()) }}"
                       class="form-control @error('documento') is-invalid @enderror" maxlength="18" autocomplete="off"
                       data-mascara-documento="{{ $tipo === 'fisica' ? 'cpf' : 'cnpj' }}" @disabled(! $tipo)>
                @error('documento')
                    <div class="invalid-feedback">{{ $message }}</div>
                @else
                    <div class="form-text">Validado e único quando informado. O CNPJ aceita o formato novo, com letras.</div>
                @enderror
            </div>

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

        <h2 class="h6 fw-semibold mb-3 mt-2">Endereço</h2>
        @include('partials.endereco-campos', ['dono' => $editando ? $fornecedor : null])

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
    <script src="{{ asset('js/documento.js') }}"></script>
    <script src="{{ asset('js/viacep.js') }}"></script>
@endpush
