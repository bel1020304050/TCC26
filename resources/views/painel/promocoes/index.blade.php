@extends('layouts.painel')

@section('titulo', 'Promoções')
@section('trilha', 'Painel › Promoções')

@section('conteudo')
{{-- Lista de promoções com pesquisa, filtro de situação e inativar/reativar --}}
@php $podeEditar = auth()->user()->can('create', \App\Models\Promocao::class); @endphp

<form method="GET" action="{{ route('painel.promocoes.index') }}" class="d-flex flex-wrap gap-2 mb-3" role="search">
    <input type="search" name="busca" value="{{ $busca }}" class="form-control" style="flex: 1 1 260px;"
           placeholder="Pesquisar por nome ou código" aria-label="Pesquisar por nome ou código">
    <select name="situacao" class="form-select" style="width: 190px;" aria-label="Filtrar por situação">
        <option value="">Situação: Todas</option>
        @foreach (\App\Models\Promocao::SITUACOES as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($situacao === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
    @if ($busca !== '' || $situacao)
        <a href="{{ route('painel.promocoes.index') }}" class="btn btn-link text-decoration-none">Limpar</a>
    @endif
    @if ($podeEditar)
        <a href="{{ route('painel.promocoes.create') }}" class="btn btn-mm ms-auto"><i class="bi bi-plus-lg"></i> Nova promoção</a>
    @endif
</form>

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Promoção</th><th>Modo</th><th>Código</th><th>Desconto</th><th>Aplicação</th><th>Período</th><th>Situação</th>
                    @if ($podeEditar) <th class="text-end">Ações</th> @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($promocoes as $promocao)
                    <tr>
                        <td class="fw-semibold">{{ $promocao->nome }}</td>
                        <td>{{ $promocao->modoLabel() }}</td>
                        <td class="font-monospace">{{ $promocao->codigo ?? '—' }}</td>
                        <td>{{ $promocao->descontoLabel() }}</td>
                        <td>{{ $promocao->aplicacaoLabel() }}</td>
                        <td class="text-nowrap">{{ $promocao->periodoLabel() }}</td>
                        <td><span class="badge rounded-pill {{ $promocao->corSituacao() }}">{{ $promocao->situacaoLabel() }}</span></td>
                        @if ($podeEditar)
                            <td class="text-end text-nowrap">
                                <a href="{{ route('painel.promocoes.edit', $promocao) }}" class="btn btn-sm btn-outline-secondary">Editar</a>
                                <x-modal-status :id="'promocao-'.$promocao->id" :ativo="$promocao->isAtivo()" tipo="promoção"
                                    :acao-inativar="route('painel.promocoes.inativar', $promocao)"
                                    :acao-reativar="route('painel.promocoes.reativar', $promocao)"
                                    :titulo="$promocao->nome.($promocao->codigo ? ' · cupom '.$promocao->codigo : '').' · '.$promocao->descontoLabel()">
                                    @if ($promocao->isAtivo())
                                        {{ $promocao->modo === 'cupom' ? 'O cupom deixa de ser aceito' : 'O desconto deixa de ser aplicado' }} em novos carrinhos.
                                        Descontos já registrados em pedidos não são recalculados. A promoção pode ser reativada depois.
                                    @else
                                        A promoção volta a valer dentro do período ({{ $promocao->periodoLabel() }}).
                                    @endif
                                </x-modal-status>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma promoção encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $promocoes->total() }} registro(s) · o cliente vê apenas promoções Ativas e Vigentes</span>
    {{ $promocoes->links() }}
</div>
@endsection
