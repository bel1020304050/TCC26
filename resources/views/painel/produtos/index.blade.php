@extends('layouts.painel')

@section('titulo', 'Produtos')
@section('trilha', 'Painel › Produtos')

@section('conteudo')
{{-- Story 3.4 – Inativar Produto (Figura 5) e listagem administrativa --}}
@php
    $usuario = auth()->user();
    $podeCadastrar = $usuario->can('create', \App\Models\Produto::class);
    $podeAlterarStatus = $usuario->isAdmin();
@endphp

<form method="GET" action="{{ route('painel.produtos.index') }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
    <input type="search" name="busca" value="{{ $busca }}" class="form-control" style="flex: 1 1 240px;"
           placeholder="Pesquisar por nome" aria-label="Pesquisar por nome">
    <select name="categoria" class="form-select" style="width: 190px;" aria-label="Filtrar por categoria">
        <option value="">Categoria: Todas</option>
        @foreach ($categorias as $categoria)
            <option value="{{ $categoria->id }}" @selected($categoriaId === $categoria->id)>{{ $categoria->nome }}</option>
        @endforeach
    </select>
    <select name="status" class="form-select" style="width: 160px;" aria-label="Filtrar por status">
        <option value="">Status: Todos</option>
        <option value="ativo" @selected($status === 'ativo')>Ativos</option>
        <option value="inativo" @selected($status === 'inativo')>Inativos</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
    @if ($busca !== '' || $status || $categoriaId)
        <a href="{{ route('painel.produtos.index') }}" class="btn btn-link text-decoration-none">Limpar</a>
    @endif
    @if ($podeCadastrar)
        <a href="{{ route('painel.produtos.create') }}" class="btn btn-mm ms-auto"><i class="bi bi-plus-lg"></i> Novo produto</a>
    @endif
</form>

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 56px;"></th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th>Tam. / Cor</th>
                    <th class="text-end">Preço</th>
                    <th class="text-center">Estoque</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($produtos as $produto)
                    <tr>
                        <td><img src="{{ $produto->imagemUrl() }}" alt="" class="rounded" width="44" height="44" style="object-fit: cover;"></td>
                        <td>
                            <div class="fw-semibold">{{ $produto->nome }}</div>
                            <div class="text-muted small">{{ $produto->fornecedor?->nome ?? 'Sem fornecedor' }}</div>
                        </td>
                        <td>{{ $produto->categoria->nome }}</td>
                        <td class="text-nowrap">{{ $produto->variacao() }}</td>
                        <td class="text-end text-nowrap">{{ $produto->precoFormatado() }}</td>
                        <td class="text-center">
                            {{-- Destaque de estoque baixo (10 ou menos), igual ao relatório 3.33 --}}
                            <span class="{{ $produto->estoque <= 10 ? 'fw-semibold text-warning-emphasis' : '' }}">{{ $produto->estoque }}</span>
                        </td>
                        <td><x-status-badge :status="$produto->status" /></td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('painel.produtos.edit', $produto) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                            @if ($podeAlterarStatus)
                                <x-modal-status :id="'produto-'.$produto->id" :ativo="$produto->isAtivo()" tipo="produto"
                                    :acao-inativar="route('painel.produtos.inativar', $produto)"
                                    :acao-reativar="route('painel.produtos.reativar', $produto)"
                                    :titulo="$produto->nome.' – '.$produto->variacao()">
                                    @if ($produto->isAtivo())
                                        O produto deixa de aparecer na vitrine. Pedidos e histórico de vendas são mantidos,
                                        e ele pode ser reativado depois.
                                    @else
                                        O produto volta a aparecer na vitrine (se tiver estoque).
                                    @endif
                                </x-modal-status>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhum produto encontrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $produtos->total() }} registro(s) · estoque em destaque = 10 unidades ou menos</span>
    {{ $produtos->links() }}
</div>
@endsection
