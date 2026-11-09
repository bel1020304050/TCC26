@extends('layouts.loja')

@section('titulo', 'Meu carrinho')

@section('conteudo')
{{-- Carrinho: itens, quantidades, cupom e totais --}}
<x-etapas-compra :atual="1" />
<h1 class="h3 mb-3">Meu carrinho</h1>

@if (! $carrinho->isAberto() && $carrinho->pedido)
    <div class="alert alert-warning">
        <i class="bi bi-lock me-1"></i>
        Este carrinho gerou o pedido <strong>{{ $carrinho->pedido->numero() }}</strong>, que aguarda pagamento, e está bloqueado para alterações.
        <a href="{{ route('loja.pedidos.show', $carrinho->pedido) }}" class="alert-link">Ver pedido</a>
    </div>
@endif

@if ($carrinho->itens->isEmpty())
    <div class="mm-card p-5 text-center text-muted">
        <i class="bi bi-bag fs-2 d-block mb-2"></i>
        Seu carrinho está vazio.
        <div class="mt-3"><a href="{{ route('loja.inicio') }}" class="btn btn-mm">Ver produtos</a></div>
    </div>
@else
    <div class="row g-3">
        <div class="col-lg-8 d-flex flex-column gap-2">
            @foreach ($carrinho->itens as $item)
                @php $produto = $item->produto; @endphp
                <div class="mm-card p-3 d-flex flex-wrap align-items-center gap-3 {{ isset($problemas[$item->id]) ? 'border-danger' : '' }}">
                    <img src="{{ $produto->imagemUrl() }}" alt="" class="rounded mm-mini">
                    <div class="flex-grow-1" style="min-width: 160px;">
                        <div class="fw-semibold">{{ $produto->nome }}</div>
                        <div class="text-muted small">Tamanho {{ $produto->tamanho }} · Cor {{ $produto->cor }}</div>
                        <div class="small">{{ $produto->precoFormatado() }}</div>
                    </div>

                    <div class="text-center">
                        @if ($carrinho->isAberto())
                            {{-- Cada botão envia a nova quantidade; o servidor confere o estoque --}}
                            <form method="POST" action="{{ route('loja.carrinho.atualizar', $item) }}" class="btn-group btn-group-sm" role="group" aria-label="Quantidade">
                                @csrf @method('PATCH')
                                <button type="submit" name="quantidade" value="{{ $item->quantidade - 1 }}" class="btn btn-outline-secondary"
                                        @disabled($item->quantidade <= 1) aria-label="Diminuir">−</button>
                                <span class="btn btn-outline-secondary disabled text-dark fw-semibold" style="min-width: 42px;">{{ $item->quantidade }}</span>
                                <button type="submit" name="quantidade" value="{{ $item->quantidade + 1 }}" class="btn btn-outline-secondary"
                                        @disabled($item->quantidade >= $produto->estoque) aria-label="Aumentar">+</button>
                            </form>
                        @else
                            <span class="fw-semibold">{{ $item->quantidade }}</span>
                        @endif
                        <div class="text-muted small mt-1">Disponível: {{ $produto->estoque }}</div>
                    </div>

                    <div class="fw-semibold text-end" style="width: 110px;">R$ {{ number_format($item->subtotal(), 2, ',', '.') }}</div>

                    @if ($carrinho->isAberto())
                        <form method="POST" action="{{ route('loja.carrinho.remover', $item) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-link text-decoration-none mm-link-rosa">Remover</button>
                        </form>
                    @endif

                    @isset($problemas[$item->id])
                        <div class="w-100 small text-danger"><i class="bi bi-exclamation-circle"></i> {{ $problemas[$item->id] }}</div>
                    @endisset
                </div>
            @endforeach
        </div>

        <div class="col-lg-4">
            <div class="mm-card p-4">
                {{-- Cupom: o servidor confere se está Ativo e Vigente antes de aplicar --}}
                @if ($carrinho->cupom)
                    <label class="form-label">Cupom</label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge text-bg-light border fs-6 font-monospace">{{ $carrinho->cupom }}</span>
                        @if ($carrinho->isAberto())
                            <form method="POST" action="{{ route('loja.carrinho.cupom.remover') }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-link btn-sm text-decoration-none mm-link-rosa p-0">Remover</button>
                            </form>
                        @endif
                    </div>
                @elseif ($carrinho->isAberto())
                    <form method="POST" action="{{ route('loja.carrinho.cupom') }}" class="mb-2">
                        @csrf
                        <label class="form-label" for="cupom">Cupom</label>
                        <div class="input-group">
                            <input type="text" id="cupom" name="cupom" value="{{ old('cupom') }}" maxlength="30"
                                   class="form-control text-uppercase @error('cupom') is-invalid @enderror" placeholder="Ex.: MARY20">
                            <button class="btn btn-outline-secondary" type="submit">Aplicar</button>
                        </div>
                        @error('cupom') <div class="small text-danger mt-1">{{ $message }}</div> @enderror
                    </form>
                @endif

                @if ($totais['promocao'])
                    <div class="alert alert-success small py-2 mb-3">
                        <i class="bi bi-tag"></i>
                        {{ $totais['promocao']->modo === 'cupom' ? 'Cupom aplicado' : 'Promoção aplicada' }}:
                        <strong>{{ $totais['promocao']->nome }}</strong> ({{ $totais['promocao']->descontoLabel() }}).
                        Só o maior desconto válido é aplicado.
                    </div>
                @else
                    <div class="mb-3"></div>
                @endif

                <div class="d-flex justify-content-between mb-1"><span>Subtotal</span><span>R$ {{ number_format($totais['subtotal'], 2, ',', '.') }}</span></div>
                <div class="d-flex justify-content-between mb-2 text-success"><span>Desconto</span><span>- R$ {{ number_format($totais['desconto'], 2, ',', '.') }}</span></div>
                <hr>
                <div class="d-flex justify-content-between fs-5 fw-bold mb-3"><span>Total</span><span class="mm-preco">R$ {{ number_format($totais['total'], 2, ',', '.') }}</span></div>

                @if ($carrinho->isAberto())
                    <a href="{{ route('loja.pedidos.confirmar') }}" class="btn btn-mm w-100 {{ $problemas ? 'disabled' : '' }}"
                       @if ($problemas) aria-disabled="true" @endif>Finalizar compra</a>
                    @if ($problemas)
                        <div class="small text-danger mt-2">Ajuste os itens destacados para continuar.</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
