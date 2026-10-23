@extends('layouts.painel')

@section('titulo', 'Fornecedores')
@section('trilha', 'Painel › Fornecedores')

@section('conteudo')
{{-- Story 3.27 – Consultar Fornecedores (Figura 28) e 3.29 – Inativar (Figura 30) --}}
@php $podeAlterar = auth()->user()->can('create', \App\Models\Fornecedor::class); @endphp

<x-filtro-lista :acao="route('painel.fornecedores.index')" :busca="$busca" :status="$status"
    placeholder="Pesquisar por nome ou CPF/CNPJ"
    :novo="$podeAlterar ? route('painel.fornecedores.create') : null" novo-texto="Novo fornecedor" />

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fornecedor</th>
                    <th>CPF / CNPJ</th>
                    <th>Telefone</th>
                    <th>E-mail</th>
                    <th class="text-center">Produtos ativos</th>
                    <th>Status</th>
                    @if ($podeAlterar) <th class="text-end">Ações</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($fornecedores as $fornecedor)
                    <tr>
                        <td class="fw-semibold">{{ $fornecedor->nome }}</td>
                        <td class="text-nowrap">{{ $fornecedor->documentoFormatado() ?? '—' }}</td>
                        <td class="text-nowrap">{{ $fornecedor->telefone ?: '—' }}</td>
                        <td>{{ $fornecedor->email ?: '—' }}</td>
                        <td class="text-center">{{ $fornecedor->produtos_ativos_count }}</td>
                        <td><x-status-badge :status="$fornecedor->status" /></td>
                        @if ($podeAlterar)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('painel.fornecedores.edit', $fornecedor) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                <x-modal-status :id="'fornecedor-'.$fornecedor->id" :ativo="$fornecedor->isAtivo()" tipo="fornecedor"
                                    :acao-inativar="route('painel.fornecedores.inativar', $fornecedor)"
                                    :acao-reativar="route('painel.fornecedores.reativar', $fornecedor)"
                                    :titulo="$fornecedor->nome.' · '.$fornecedor->produtos_ativos_count.' produto(s) ativo(s) vinculado(s)'"
                                    :bloqueio="$fornecedor->produtos_ativos_count > 0
                                        ? 'Há '.$fornecedor->produtos_ativos_count.' produto(s) ativo(s) deste fornecedor. Troque o fornecedor desses produtos ou inative-os antes de inativar o fornecedor.'
                                        : null">
                                    @if ($fornecedor->isAtivo())
                                        O fornecedor deixa de aparecer como opção em novos produtos.
                                        O cadastro e o histórico são mantidos, e ele pode ser reativado depois.
                                    @else
                                        O fornecedor volta a aparecer como opção no cadastro de produtos.
                                    @endif
                                </x-modal-status>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum fornecedor encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $fornecedores->total() }} registro(s) · fornecedores inativos continuam pesquisáveis</span>
    {{ $fornecedores->links() }}
</div>
@endsection
