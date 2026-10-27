@extends('layouts.loja')

@section('titulo', 'Pedido '.$pedido->numero())

@section('conteudo')
{{-- Story 3.19 – Detalhes do pedido --}}
@if ($pedido->status === \App\Models\Pedido::AGUARDANDO_PAGAMENTO)
    <x-etapas-compra :atual="3" />
@endif

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <h1 class="h3 mb-0">Pedido {{ $pedido->numero() }}</h1>
    <x-status-pedido :status="$pedido->status" />
    <span class="text-muted small ms-1">feito em {{ $pedido->created_at->format('d/m/Y \à\s H:i') }}</span>
    <a href="{{ route('loja.pedidos.index') }}" class="btn btn-outline-secondary btn-sm ms-auto">Meus pedidos</a>
</div>

@if ($pedido->status === \App\Models\Pedido::AGUARDANDO_PAGAMENTO)
    <div class="alert alert-info d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-hourglass-split"></i>
        <span>Próximo passo: pagamento por PIX, Cartão ou Boleto. <strong>A tela de pagamento chega na Fase 6.</strong></span>
        <button type="button" class="btn btn-mm btn-sm ms-auto" disabled>Pagar</button>
    </div>
@endif

@include('partials.pedido-detalhes', ['pedido' => $pedido])
@endsection
