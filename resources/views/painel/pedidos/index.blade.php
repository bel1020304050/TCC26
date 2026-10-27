@extends('layouts.painel')

@section('titulo', 'Pedidos')
@section('trilha', 'Painel › Pedidos')

@section('conteudo')
{{-- Story 3.19 – Consulta de pedidos na área administrativa (Figura 21) --}}
<form method="GET" action="{{ route('painel.pedidos.index') }}" class="d-flex flex-wrap gap-2 mb-3 align-items-center" role="search">
    <input type="search" name="busca" value="{{ $busca }}" class="form-control" style="flex: 1 1 240px;"
           placeholder="Pesquisar por nº do pedido ou cliente" aria-label="Pesquisar por número do pedido ou cliente">
    <div class="input-group" style="width: 330px;">
        <span class="input-group-text">Período</span>
        <input type="date" name="de" value="{{ $de }}" class="form-control" aria-label="Data inicial">
        <input type="date" name="ate" value="{{ $ate }}" class="form-control" aria-label="Data final">
    </div>
    <select name="status" class="form-select" style="width: 210px;" aria-label="Filtrar por status">
        <option value="">Status: Todos</option>
        @foreach (\App\Models\Pedido::STATUS as $valor => $rotulo)
            <option value="{{ $valor }}" @selected($status === $valor)>{{ $rotulo }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search"></i> Filtrar</button>
    @if ($busca !== '' || $status || $de || $ate)
        <a href="{{ route('painel.pedidos.index') }}" class="btn btn-link text-decoration-none">Limpar</a>
    @endif
</form>

<div class="mm-card overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>Pedido</th><th>Data</th><th>Cliente</th><th class="text-end">Total</th><th>Status</th><th class="text-end">Ações</th></tr>
            </thead>
            <tbody>
                @forelse ($pedidos as $pedido)
                    <tr>
                        <td class="fw-semibold">{{ $pedido->numero() }}</td>
                        <td class="text-nowrap">{{ $pedido->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <div>{{ $pedido->cliente->name }}</div>
                            <div class="text-muted small">{{ $pedido->cliente->email }}</div>
                        </td>
                        <td class="text-end text-nowrap">{{ $pedido->valor('total') }}</td>
                        <td><x-status-pedido :status="$pedido->status" /></td>
                        <td class="text-end"><a href="{{ route('painel.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-secondary">Detalhes</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Nenhum pedido encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-2">
    <span class="text-muted small">{{ $pedidos->total() }} registro(s) · atualizar status e cancelar chegam na Fase 7</span>
    {{ $pedidos->links() }}
</div>
@endsection
