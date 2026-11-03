@extends('layouts.painel')

@section('titulo', 'Pedido '.$pedido->numero())
@section('trilha', 'Painel › Pedidos › '.$pedido->numero())

@section('conteudo')
<div class="mm-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
    <x-status-pedido :status="$pedido->status" />
    <span class="text-muted small">Criado em {{ $pedido->created_at->format('d/m/Y H:i') }}</span>
    <span><i class="bi bi-person"></i> {{ $pedido->cliente->name }}
        <span class="text-muted small">· {{ $pedido->cliente->contatoPainel() }}</span>
    </span>
    <div class="ms-auto d-flex gap-2">
        @if ($pedido->proximoStatusManual())
            @include('partials.modal-status-pedido', ['pedido' => $pedido])
        @endif
        @can('cancelar', $pedido)
            @include('partials.modal-cancelar-pedido', ['pedido' => $pedido, 'acao' => route('painel.pedidos.cancelar', $pedido), 'motivoObrigatorio' => true])
        @endcan
        <a href="{{ route('painel.pedidos.index') }}" class="btn btn-outline-secondary btn-sm">Voltar</a>
    </div>
</div>

@php $bloqueio = \App\Policies\PedidoPolicy::motivoBloqueioCancelamento(auth()->user(), $pedido); @endphp
@if ($bloqueio && in_array($pedido->status, [\App\Models\Pedido::PAGO, \App\Models\Pedido::EM_SEPARACAO], true))
    <div class="alert alert-light border small py-2"><i class="bi bi-info-circle"></i> {{ $bloqueio }}</div>
@endif
@if ($errors->has('motivo'))
    <div class="alert alert-danger small py-2">{{ $errors->first('motivo') }}</div>
@endif

@include('partials.pedido-detalhes', ['pedido' => $pedido, 'comResponsavel' => true])
@endsection
