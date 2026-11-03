@extends('layouts.painel')

@section('titulo', $relatorio->titulo())
@section('trilha', 'Painel › Relatórios › '.str($relatorio->titulo())->after('Relatório de ')->ucfirst())

@section('conteudo')
{{-- Tela comum dos relatórios (Figuras 33, 34 e 35): filtros, indicadores, tabela e exportação --}}
<form method="GET" class="mm-card p-3 mb-3" role="search">
    <div class="row g-2 align-items-end">
        @include($filtrosView)
        <div class="col-md-auto d-grid">
            <button type="submit" class="btn btn-mm">Gerar relatório</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    @foreach ($relatorio->indicadores() as $rotulo => $valor)
        <div class="col-md-4">
            <div class="mm-card p-3 h-100">
                <div class="text-muted small">{{ $rotulo }}</div>
                <div class="fs-3 fw-bold">{{ $valor }}</div>
            </div>
        </div>
    @endforeach
</div>

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>@foreach ($relatorio->colunas() as $coluna) <th>{{ $coluna }}</th> @endforeach</tr>
            </thead>
            <tbody>
                @forelse ($registros as $registro)
                    <tr class="{{ $relatorio->destaque($registro) ? 'mm-linha-destaque' : '' }}">
                        @foreach ($relatorio->linha($registro) as $i => $celula)
                            <td class="{{ $relatorio->destaque($registro) && $relatorio->colunas()[$i] === 'Estoque' ? 'fw-bold text-warning-emphasis' : '' }}">
                                @if ($i === $relatorio->colunaStatus())
                                    <x-status-pedido :status="$registro->status" />
                                @elseif ($celula === 'Cliente removido')
                                    <span class="text-muted">{{ $celula }}</span>
                                @else
                                    {{ $celula }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($relatorio->colunas()) }}" class="text-center text-muted py-4">Nenhum resultado para os filtros escolhidos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
    <span class="text-muted small">Exibindo {{ $registros->count() }} de {{ $registros->total() }} · {{ $relatorio->rodape() }}</span>
    <span class="small fw-semibold">
        Exportar
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'pdf', 'page' => null]) }}" target="_blank" class="text-decoration-none mm-laranja">PDF</a> ·
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv', 'page' => null]) }}" class="text-decoration-none mm-laranja">CSV</a>
    </span>
</div>
<div class="mt-2">{{ $registros->links() }}</div>
@endsection
