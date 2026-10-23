@extends('layouts.painel')

@section('titulo', 'Categorias')
@section('trilha', 'Painel › Categorias')

@section('conteudo')
{{-- Story 3.15 – Consultar Categorias (Figura 16) e 3.17 – Inativar (Figura 18) --}}
@php $podeAlterar = auth()->user()->can('create', \App\Models\Categoria::class); @endphp

<x-filtro-lista :acao="route('painel.categorias.index')" :busca="$busca" :status="$status"
    placeholder="Pesquisar categoria por nome"
    :novo="$podeAlterar ? route('painel.categorias.create') : null" novo-texto="Nova categoria" />

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th class="text-center">Produtos ativos</th>
                    <th class="text-center">Produtos vinculados</th>
                    <th>Status</th>
                    @if ($podeAlterar) <th class="text-end">Ações</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($categorias as $categoria)
                    <tr>
                        <td class="fw-semibold">{{ $categoria->nome }}</td>
                        <td class="text-muted">{{ $categoria->descricao ?: '—' }}</td>
                        <td class="text-center">{{ $categoria->produtos_ativos_count }}</td>
                        <td class="text-center">{{ $categoria->produtos_vinculados_count }}</td>
                        <td><x-status-badge :status="$categoria->status" /></td>
                        @if ($podeAlterar)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('painel.categorias.edit', $categoria) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                <x-modal-status :id="'categoria-'.$categoria->id" :ativo="$categoria->isAtivo()" tipo="categoria"
                                    :acao-inativar="route('painel.categorias.inativar', $categoria)"
                                    :acao-reativar="route('painel.categorias.reativar', $categoria)"
                                    :titulo="$categoria->nome.' · '.$categoria->produtos_vinculados_count.' produto(s) vinculado(s)'"
                                    :bloqueio="$categoria->produtos_ativos_count > 0
                                        ? 'A categoria tem '.$categoria->produtos_ativos_count.' produto(s) ativo(s). Mova os produtos para outra categoria ou inative-os antes de inativar a categoria.'
                                        : null">
                                    @if ($categoria->isAtivo())
                                        A categoria deixa de aparecer nos filtros da vitrine e no cadastro de produtos.
                                        O histórico é mantido e ela pode ser reativada depois.
                                    @else
                                        A categoria volta a aparecer na vitrine e no cadastro de produtos.
                                    @endif
                                </x-modal-status>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Nenhuma categoria encontrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $categorias->total() }} registro(s) · o cliente vê apenas as categorias ativas, nos filtros da vitrine</span>
    {{ $categorias->links() }}
</div>
@endsection
