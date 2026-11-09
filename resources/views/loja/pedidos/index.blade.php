@extends('layouts.loja')

@section('titulo', 'Meus pedidos')

@section('conteudo')
{{-- "Meus pedidos": lista dos pedidos do cliente, do mais recente ao mais antigo --}}
<h1 class="h3 mb-3">Meus pedidos</h1>

<div class="row g-3">
<div class="col-lg-3">@include('loja.conta._menu')</div>
<div class="col-lg-9">

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
                            <td class="text-end text-nowrap">
                                <a href="{{ route('loja.pedidos.show', $pedido) }}" class="btn btn-sm btn-outline-secondary">Detalhes</a>
                                @if ($pedido->aguardandoPagamento())
                                    <a href="{{ route('loja.pagamento.create', $pedido) }}" class="btn btn-sm btn-mm">Pagar</a>
                                    @include('partials.modal-cancelar-pedido', ['pedido' => $pedido, 'acao' => route('loja.pedidos.cancelar', $pedido), 'motivoObrigatorio' => false])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $pedidos->links() }}</div>
@endif
</div>
</div>
@endsection
