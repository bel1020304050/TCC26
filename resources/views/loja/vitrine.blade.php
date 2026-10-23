@extends('layouts.loja')

@section('titulo', $filtros['busca'] !== '' ? 'Busca: '.$filtros['busca'] : 'Início')

@section('conteudo')
{{-- Story 3.2 – Visualizar e Pesquisar Produtos (Figura 3) --}}
@guest
    <div class="mm-card p-4 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h1 class="h4 mb-1">Bem-vinda(o) à Mary Modas</h1>
            <p class="text-muted mb-0">Moda feminina em Umuarama, agora também online.</p>
        </div>
        <div>
            <a href="{{ route('register') }}" class="btn btn-mm me-2">Criar conta</a>
            <a href="{{ route('login') }}" class="btn btn-outline-secondary">Entrar</a>
        </div>
    </div>
@endguest

{{-- Filtros: pesquisa por nome, categoria (só ativas), tamanho, cor e ordenação --}}
<form method="GET" action="{{ route('loja.inicio') }}" class="row g-2 align-items-center mb-2" role="search">
    <div class="col-lg-3">
        <input type="search" name="busca" value="{{ $filtros['busca'] }}" class="form-control" placeholder="Pesquisar produtos..." aria-label="Pesquisar produtos">
    </div>
    <div class="col-6 col-lg-2">
        <select name="categoria" class="form-select" aria-label="Categoria">
            <option value="">Categoria: Todas</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected($filtros['categoria'] === $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-lg-2">
        <select name="tamanho" class="form-select" aria-label="Tamanho">
            <option value="">Tamanho: Todos</option>
            @foreach ($tamanhos as $tamanho)
                <option value="{{ $tamanho }}" @selected($filtros['tamanho'] === $tamanho)>{{ $tamanho }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-lg-2">
        <select name="cor" class="form-select" aria-label="Cor">
            <option value="">Cor: Todas</option>
            @foreach ($cores as $cor)
                <option value="{{ $cor }}" @selected($filtros['cor'] === $cor)>{{ $cor }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-6 col-lg-2">
        <select name="ordem" class="form-select" aria-label="Ordenar">
            <option value="recentes" @selected($filtros['ordem'] === 'recentes')>Mais recentes</option>
            <option value="menor_preco" @selected($filtros['ordem'] === 'menor_preco')>Menor preço</option>
            <option value="maior_preco" @selected($filtros['ordem'] === 'maior_preco')>Maior preço</option>
        </select>
    </div>
    <div class="col-lg-1 d-grid">
        <button type="submit" class="btn btn-mm" aria-label="Pesquisar" title="Pesquisar"><i class="bi bi-search"></i></button>
    </div>
</form>

<p class="text-muted small mb-3">
    {{ $produtos->total() }} produto(s) encontrado(s)
    @if ($filtros['busca'] !== '') para “{{ $filtros['busca'] }}” @endif
    @if ($filtros['busca'] !== '' || $filtros['categoria'] || $filtros['tamanho'] !== '' || $filtros['cor'] !== '')
        · <a href="{{ route('loja.inicio') }}" class="text-decoration-none">Limpar filtros</a>
    @endif
</p>

@if ($produtos->isEmpty())
    <div class="mm-card p-5 text-center text-muted">
        <i class="bi bi-search fs-2 d-block mb-2"></i>
        Nenhum produto encontrado. Tente outro termo ou limpe os filtros.
    </div>
@else
    {{-- Cada combinação de tamanho e cor é um card separado (MVP) --}}
    <div class="row row-cols-2 row-cols-md-3 row-cols-lg-4 g-3">
        @foreach ($produtos as $produto)
            <div class="col">
                <div class="mm-card h-100 p-2 mm-produto">
                    <img src="{{ $produto->imagemUrl() }}" alt="{{ $produto->nome }}" class="w-100 rounded mb-2" loading="lazy">
                    <div class="px-1 pb-1">
                        <div class="fw-semibold">{{ $produto->nome }}</div>
                        <div class="text-muted small mb-1">{{ $produto->categoria->nome }} · {{ $produto->tamanho }} · {{ $produto->cor }}</div>
                        <div class="mm-preco">{{ $produto->precoFormatado() }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center mt-4">
        {{ $produtos->links() }}
    </div>
@endif

<p class="text-muted small mt-3 mb-0">
    <i class="bi bi-info-circle"></i> Carrinho na Fase 5 · Preço promocional na Fase 8.
</p>
@endsection
