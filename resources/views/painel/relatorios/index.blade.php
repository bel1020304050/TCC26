@extends('layouts.painel')

@section('titulo', 'Relatórios')
@section('trilha', 'Painel › Relatórios')

@section('conteudo')
{{-- Stories 3.32 a 3.34: Vendas e Clientes só para o Administrador; Estoque também para o Funcionário --}}
@php
    $cards = [
        ['relatorio-vendas', 'painel.relatorios.vendas', 'graph-up', 'Vendas', 'Faturamento, vendas pagas e ticket médio por período, status e forma de pagamento.'],
        ['relatorio-estoque', 'painel.relatorios.estoque', 'box-seam', 'Produtos / Estoque', 'Disponibilidade por categoria, fornecedor, tamanho e cor, com destaque para estoque baixo.'],
        ['relatorio-clientes', 'painel.relatorios.clientes', 'people', 'Clientes', 'Clientes cadastrados no período, pedidos pagos e total comprado.'],
    ];
@endphp
<div class="row g-3">
    @foreach ($cards as [$permissao, $rota, $icone, $titulo, $texto])
        @can($permissao)
            <div class="col-md-4">
                <a href="{{ route($rota) }}" class="mm-card p-4 d-block h-100 text-decoration-none text-reset mm-card-link">
                    <i class="bi bi-{{ $icone }} fs-3 mm-link-rosa"></i>
                    <h2 class="h5 mt-2">{{ $titulo }}</h2>
                    <p class="text-muted small mb-0">{{ $texto }}</p>
                </a>
            </div>
        @endcan
    @endforeach
</div>
<p class="text-muted small mt-3">Todos os relatórios podem ser exportados em CSV (planilha) ou PDF.</p>
@endsection
