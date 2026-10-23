{{-- Barra de pesquisa + filtro de status usada nas listagens do painel --}}
@props(['acao', 'busca' => '', 'status' => null, 'placeholder' => 'Pesquisar', 'novo' => null, 'novoTexto' => 'Novo'])

<form method="GET" action="{{ $acao }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
    <input type="search" name="busca" value="{{ $busca }}" class="form-control" style="flex: 1 1 260px;"
           placeholder="{{ $placeholder }}" aria-label="{{ $placeholder }}">
    <select name="status" class="form-select" style="width: 170px;" aria-label="Filtrar por status">
        <option value="">Status: Todos</option>
        <option value="ativo" @selected($status === 'ativo')>Ativos</option>
        <option value="inativo" @selected($status === 'inativo')>Inativos</option>
    </select>
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
    @if ($busca !== '' || $status)
        <a href="{{ $acao }}" class="btn btn-link text-decoration-none">Limpar</a>
    @endif
    @if ($novo)
        <a href="{{ $novo }}" class="btn btn-mm ms-auto"><i class="bi bi-plus-lg"></i> {{ $novoTexto }}</a>
    @endif
</form>
