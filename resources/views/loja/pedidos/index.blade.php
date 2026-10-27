@extends('layouts.loja')

@section('titulo', 'Meus pedidos')

@section('conteudo')
{{-- Story 3.19 – Consultar e Acompanhar Pedidos (Figura 20) --}}
<h1 class="h3 mb-3">Meus pedidos</h1>

@if ($pedidos->isEmpty())
    <div class="mm-card p-5 text-center text-muted">
        <i class="bi bi-receipt fs-2 d-block mb-2"></i>
        Você ainda não fez nenhum pedido.
        <div class="mt-3"><a href="{{ route('loja.inicio') }}" class="btn btn-mm">Ver produtos</a></div>
    </div>
@else
    <div class="mm-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Pedido</th><th>Data</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Ações</th></tr>
                </thead>
                <tbody>
                    @foreach ($pedidos as $pedido)
                        <tr>
                            <td class="fw-semibold">{{ $pedido->numero() }}</td>
                            <td>{{ $pedido->created_at->format('d/m/Y') }}</td>
                            <td class="text-end text-nowrap">{{ $pedido->valor('total') }}</td>
                            <td><x-status-pedido :status="$pedido->status" /></td>
                            <td class="text-end"><a href="{{ route('loja.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-secondary">Detalhes</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $pedidos->links() }}</div>
@endif
@endsection
