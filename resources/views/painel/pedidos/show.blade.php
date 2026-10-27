@extends('layouts.painel')

@section('titulo', 'Pedido '.$pedido->numero())
@section('trilha', 'Painel › Pedidos › '.$pedido->numero())

@section('conteudo')
<div class="mm-card p-3 mb-3 d-flex flex-wrap align-items-center gap-3">
    <x-status-pedido :status="$pedido->status" />
    <span class="text-muted small">Criado em {{ $pedido->created_at->format('d/m/Y H:i') }}</span>
    <span><i class="bi bi-person"></i> {{ $pedido->cliente->name }}
        <span class="text-muted small">· {{ $pedido->cliente->email }} @if ($pedido->cliente->telefone) · {{ $pedido->cliente->telefone }} @endif</span>
    </span>
    <a href="{{ route('painel.pedidos.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">Voltar</a>
</div>

@include('partials.pedido-detalhes', ['pedido' => $pedido, 'comResponsavel' => true])
@endsection
