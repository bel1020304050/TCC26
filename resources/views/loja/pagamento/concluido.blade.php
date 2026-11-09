@extends('layouts.loja')

@section('titulo', 'Compra concluída')

@section('conteudo')
{{-- Tela de compra concluída (pagamento aprovado) --}}
<x-etapas-compra :atual="4" />

<div class="mm-card p-5 text-center mx-auto" style="max-width: 640px;">
    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
    <h1 class="h3 mt-2">Pagamento aprovado!</h1>
    <p class="text-muted mb-4">
        O pedido <strong>{{ $pedido->numero() }}</strong> foi pago com <strong>{{ $pagamento->formaLabel() }}</strong>
        @if ($pagamento->detalhe()) ({{ $pagamento->detalhe() }}) @endif
        no valor de <strong>{{ $pagamento->valorFormatado() }}</strong>. Agora é com a gente: avisaremos cada etapa até a entrega.
    </p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="{{ route('loja.pedidos.show', $pedido) }}" class="btn btn-mm">Acompanhar pedido</a>
        <a href="{{ route('loja.inicio') }}" class="btn btn-outline-secondary">Continuar comprando</a>
    </div>
</div>
@endsection
