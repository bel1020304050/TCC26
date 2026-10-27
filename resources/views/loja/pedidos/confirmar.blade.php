@extends('layouts.loja')

@section('titulo', 'Confirmar pedido')

@section('conteudo')
{{-- Story 3.18 – Criar Pedido (Figura 19) --}}
<x-etapas-compra :atual="2" />
<h1 class="h3 mb-3">Confirmar pedido</h1>

@if ($errors->has('estoque') || $problemas)
    <div class="alert alert-danger">
        <strong>Não foi possível criar o pedido.</strong> Ajuste estes itens no carrinho:
        <ul class="mb-1 mt-2">
            @foreach ($errors->get('estoque') ?: array_values($problemas) as $mensagem)
                <li>{{ $mensagem }}</li>
            @endforeach
        </ul>
        <a href="{{ route('loja.carrinho') }}" class="alert-link">Voltar ao carrinho</a>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="mm-card p-4">
            <h2 class="h6 fw-semibold mb-3">Resumo do carrinho</h2>
            @foreach ($carrinho->itens as $item)
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <span>{{ $item->produto->nome }} — {{ $item->produto->variacao() }} · {{ $item->quantidade }} × {{ $item->produto->precoFormatado() }}</span>
                    <span class="text-nowrap">R$ {{ number_format($item->subtotal(), 2, ',', '.') }}</span>
                </div>
            @endforeach
            <hr>
            <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span>R$ {{ number_format($totais['subtotal'], 2, ',', '.') }}</span></div>
            <div class="d-flex justify-content-between text-success"><span>Desconto</span><span>- R$ {{ number_format($totais['desconto'], 2, ',', '.') }}</span></div>
            <hr>
            <div class="d-flex justify-content-between fs-5 fw-bold"><span>Total</span><span class="mm-preco">R$ {{ number_format($totais['total'], 2, ',', '.') }}</span></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="mm-card p-4 mb-3">
            <h2 class="h6 fw-semibold mb-2">Endereço de entrega</h2>
            <div>{{ $cliente->rua }}, {{ $cliente->numero }}@if ($cliente->complemento) – {{ $cliente->complemento }}@endif</div>
            <div>{{ $cliente->bairro }} · {{ $cliente->cidade }}/{{ $cliente->uf }}</div>
            <div>CEP {{ $cliente->cep }}</div>
            <p class="text-muted small mt-2 mb-0">Copiado do seu cadastro e salvo no pedido.</p>
        </div>
        <p class="text-muted small mb-2">Frete: não calculado nesta versão.</p>

        {{-- O botão se desativa após o 1º clique; mesmo assim o servidor garante 1 só pedido (RNF.01) --}}
        <form method="POST" action="{{ route('loja.pedidos.store') }}" onsubmit="this.querySelector('button').disabled = true;">
            @csrf
            <button type="submit" class="btn btn-mm w-100 py-2" @disabled($problemas)>Confirmar pedido e ir para o pagamento</button>
        </form>
        <p class="text-muted small mt-2">Ao confirmar, o pedido é criado como Aguardando Pagamento e o carrinho fica bloqueado para alterações.</p>
        <a href="{{ route('loja.carrinho') }}" class="small text-decoration-none">← Voltar ao carrinho</a>
    </div>
</div>
@endsection
