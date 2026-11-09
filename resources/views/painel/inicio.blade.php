@extends('layouts.painel')

@section('titulo', 'Início')
@section('trilha', 'Painel')

@section('conteudo')
<div class="mm-card p-4 mb-3">
    <h2 class="h5 mb-1">Olá, {{ $usuario->name }}!</h2>
    <p class="text-muted mb-0">
        Você está conectada(o) como <span class="badge badge-perfil">{{ $usuario->perfilLabel() }}</span>
    </p>
</div>

{{-- Indicadores do dia: cada card leva para a lista já filtrada --}}
@php
    $cards = [
        ['Aguardando pagamento', $resumo['aguardando'], 'hourglass-split', route('painel.pedidos.index', ['status' => 'aguardando_pagamento'])],
        ['Pedidos para separar', $resumo['separar'], 'box-seam', route('painel.pedidos.index', ['status' => 'pago'])],
        ['Produtos com estoque baixo', $resumo['estoqueBaixo'], 'exclamation-triangle', route('painel.relatorios.estoque', ['baixo' => 1, 'status' => 'ativo'])],
        ['Vendas do mês', 'R$ '.number_format($resumo['vendasMes'], 2, ',', '.'), 'graph-up',
            auth()->user()->can('relatorio-vendas') ? route('painel.relatorios.vendas', ['de' => now()->startOfMonth()->toDateString(), 'ate' => now()->toDateString()]) : null],
    ];
@endphp
<div class="row g-3 mb-3">
    @foreach ($cards as [$rotulo, $valor, $icone, $link])
        <div class="col-sm-6 col-lg-3">
            <a @if ($link) href="{{ $link }}" @endif class="mm-card p-3 d-block h-100 text-decoration-none text-reset {{ $link ? 'mm-card-link' : '' }}">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="text-muted small">{{ $rotulo }}</span>
                    <i class="bi bi-{{ $icone }} mm-link-rosa"></i>
                </div>
                <div class="fs-3 fw-bold mt-1">{{ $valor }}</div>
            </a>
        </div>
    @endforeach
</div>

<div class="mm-card overflow-hidden">
    <div class="d-flex justify-content-between align-items-center px-3 pt-3">
        <h2 class="h6 fw-semibold mb-2">Últimos pedidos</h2>
        <a href="{{ route('painel.pedidos.index') }}" class="small text-decoration-none">Ver todos</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Pedido</th><th>Data</th><th>Cliente</th><th class="text-end">Total</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse ($ultimosPedidos as $pedido)
                    <tr>
                        <td><a href="{{ route('painel.pedidos.show', $pedido) }}" class="fw-semibold text-decoration-none">{{ $pedido->numero() }}</a></td>
                        <td class="text-nowrap">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $pedido->cliente->name }}</td>
                        <td class="text-end text-nowrap">{{ $pedido->valor('total') }}</td>
                        <td><x-status-pedido :status="$pedido->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">Nenhum pedido ainda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
